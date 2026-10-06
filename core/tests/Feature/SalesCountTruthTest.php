<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\CompGrantService;
use App\Services\SettingsService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * F1 (v1.9.2) — SALES COUNT TRUTH.
 *
 * The bug: every sales surface (dashboard Achievements progress, the Earnings
 * tab, the public profile stat, the top-prompts table, the badge/CRITERIA
 * evaluator and the sale-milestone observer) summed or read
 * `prompts.sales_count` — a denormalized column NOTHING in the codebase ever
 * increments. A creator who had sold prompts on the Sikka credit rail still
 * read 0 sales, forever.
 *
 * The fix is a READ fix (no ledger change): a sale is a PAID order line that
 * names the listing (OrderItem::paidSalesCountForCreator / paidSales), which
 * both rails write identically — the Sikka rail settles the same order rows
 * as eSewa / manual.
 *
 * These tests drive the REAL purchase path (checkout → Sikka pay) rather than
 * seeding the dead column, so they can only pass when the payment actually
 * reaches the order.
 */
uses(RefreshDatabase::class);

/**
 * A published, paid listing owned by $creator (199 Sikka), WITH the active
 * product offer the checkout requires (products.prompt_id is 1-to-1).
 */
function salesTruthPrompt(User $creator, int $sikka = 199): Prompt
{
    $prompt = Prompt::factory()
        ->for($creator, 'creator')
        ->hasVersion()
        ->priced($sikka * 100)
        ->published()
        ->create(['visibility' => Prompt::VISIBILITY_PUBLIC]);

    Product::factory()->create([
        'prompt_id' => $prompt->id,
        'price_paisa' => $sikka * 100,
        'status' => Product::STATUS_ACTIVE,
    ]);

    return $prompt;
}

/** The Sales stat card on /dashboard/earnings (dd content is the number). */
function earningsSalesCount(string $html): string
{
    preg_match('/>Sales<\/p>\s*<p class="mt-2 font-mono text-3xl font-bold tracking-tight text-ink">([\d,]+)<\/p>/', $html, $match);

    return $match[1] ?? 'missing';
}

/** The public profile's "N sale(s)" stat — the number renders in the dd. */
function profileSalesCount(string $html): string
{
    preg_match('/<dd class="text-lg font-bold text-ink">([\d,]+)<\/dd>\s*<dt class="text-sm text-ink\/50">sale(s)?<\/dt>/', $html, $match);

    return $match[1] ?? 'missing';
}

/** Fund a buyer's wallet the way the top-up rail does (insert-only ledger). */
function fundSikka(User $buyer, int $amount): void
{
    SikkaTransaction::query()->create([
        'user_id' => $buyer->id,
        'type' => SikkaTransaction::TYPE_TOPUP,
        'amount_sikka' => $amount,
        'cashout_eligible' => true,
        'idempotency_key' => 'sales-truth:'.uniqid(),
        'meta' => [],
        'created_at' => now(),
    ]);
}

test('a Sikka credit purchase moves the sales count from 0 to 1 on every surface', function () {
    app(SettingsService::class)->set('engage_daily_sikka', '0');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = salesTruthPrompt($creator);
    $buyer = User::factory()->create();

    // ---- Before the sale: 0 everywhere -------------------------------------
    expect(OrderItem::paidSalesCountForCreator($creator))->toBe(0);

    $earnings = $this->actingAs($creator)->get(route('dashboard.earnings'))->assertOk()->getContent();
    expect(earningsSalesCount($earnings))->toBe('0');

    // ---- The real purchase: checkout → pay with credits --------------------
    fundSikka($buyer, 500);

    $this->actingAs($buyer)->post(route('checkout.prompts.buy', $prompt))->assertRedirect();
    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    $this->actingAs($buyer)
        ->post(route('checkout.sikka.pay', $order))
        ->assertRedirect(route('purchases.index'));

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and($order->currency)->toBe(Order::CURRENCY_SIKKA)
        ->and(LicenseGrant::query()->where('user_id', $buyer->id)->count())->toBe(1)
        ->and(OrderItem::paidSalesCountForCreator($creator))->toBe(1);

    // ---- After: 1 on the dashboard stat -----------------------------------
    $earnings = $this->actingAs($creator)->get(route('dashboard.earnings'))->assertOk()->getContent();
    expect(earningsSalesCount($earnings))->toBe('1');

    // ---- 1 on the public profile stat -------------------------------------
    $profile = $this->get(route('creators.show', $creator))->assertOk()->getContent();
    expect(profileSalesCount($profile))->toBe('1');

    // ---- 1/10 on the Achievements progress row (badges can now fire) ------
    $achievements = $this->actingAs($creator)->get(route('dashboard', ['tab' => 'achievements']))->assertOk()->getContent();
    expect($achievements)->toContain('1/10');

    // ---- the per-listing number in the top-prompts table ------------------
    $stats = $this->actingAs($creator)->get(route('dashboard', ['tab' => 'stats']))->assertOk()->getContent();
    expect($stats)->toContain('1 sales');
});

test('the sales count is rail-agnostic: an approved manual NPR order counts too', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = salesTruthPrompt($creator);
    $buyer = User::factory()->create();

    // The NPR rail: order created at checkout, settled by the payment
    // approval path (WalletService::settleOrder) — the same order rows the
    // Sikka rail flips, so the count must not care which rail paid.
    $this->actingAs($buyer)->post(route('checkout.prompts.buy', $prompt))->assertRedirect();
    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    expect(OrderItem::paidSalesCountForCreator($creator))->toBe(0);

    app(WalletService::class)->settleOrder($order, 'manual');

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and(OrderItem::paidSalesCountForCreator($creator))->toBe(1);
});

test('comp grants are not sales', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = salesTruthPrompt($creator);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $recipient = User::factory()->create();

    app(CompGrantService::class)->grant($recipient, $prompt, $admin, 'press copy');

    expect(LicenseGrant::query()->where('user_id', $recipient->id)->count())->toBe(1)
        ->and(OrderItem::paidSalesCountForCreator($creator))->toBe(0);
});

test('the legacy sales_count column is dead data and no surface reads it', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    // A listing whose denormalized column claims 99 sales but which has NO
    // paid order line: the whole product must report 0 — nothing writes that
    // column, so trusting it is exactly the F1 bug.
    $phantom = Prompt::factory()->for($creator, 'creator')->hasVersion()->published()
        ->create(['sales_count' => 99]);

    expect(OrderItem::paidSalesCountForCreator($creator))->toBe(0);

    $profile = $this->get(route('creators.show', $creator))->assertOk()->getContent();
    expect(profileSalesCount($profile))->toBe('0');

    // …and a real paid line counts even while the legacy column reads 0.
    recordPaidSale($phantom, User::factory()->create());

    expect($phantom->fresh()->sales_count)->toBe(99) // untouched, as it must be
        ->and(OrderItem::paidSalesCountForCreator($creator))->toBe(1);
});
