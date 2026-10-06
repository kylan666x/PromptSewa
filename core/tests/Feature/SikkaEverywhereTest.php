<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S1 (v1.9.0) — the wallet is visible everywhere and NPR is gone from the
 * buyer surfaces.
 *
 * Locks:
 *   - the navbar wallet chip (desktop + mobile) for signed-in users,
 *     carrying the Sikka mark + integer and pointing at the earnings ledger;
 *   - no NPR on prompt cards, the detail page or checkout — Sikka only;
 *   - the earnings tab is Sikka-only (spendable, cash-out eligible, ledger,
 *     withdrawal queue) with zero NPR rendering;
 *   - the kill-switch is retired: the economy ships ON, the desk hides the
 *     toggle, and the top-up storefront exists with nobody flipping anything.
 */
function sikkaEverywhereQuietEngagement(): void
{
    // The daily-visit middleware credits +1 Sikka on the first
    // authenticated request of the day, and publishing/rating emit too.
    // These locks are about the LEDGER CHROME, so the emitters are muted
    // and the arithmetic below stays exact.
    $settings = app(SettingsService::class);
    $settings->set('engage_daily_sikka', '0');
    $settings->set('engage_publish_sikka', '0');
    $settings->set('engage_rating_sikka', '0');
}

function sikkaEverywhereBuyer(int $balance = 0): User
{
    $buyer = User::factory()->create();

    if ($balance > 0) {
        SikkaTransaction::query()->create([
            'user_id' => $buyer->id,
            'type' => SikkaTransaction::TYPE_TOPUP,
            'amount_sikka' => $balance,
            'cashout_eligible' => true,
            'idempotency_key' => 'everywhere:topup:'.$buyer->id,
            'meta' => [],
            'created_at' => now(),
        ]);
    }

    return $buyer;
}

function sikkaEverywhereCredit(User $user, int $amount): void
{
    SikkaTransaction::query()->create([
        'user_id' => $user->id,
        'type' => SikkaTransaction::TYPE_SALE_CREDIT,
        'amount_sikka' => $amount,
        'cashout_eligible' => true,
        'idempotency_key' => 'everywhere:credit:'.$user->id,
        'meta' => [],
        'created_at' => now(),
    ]);
}

function sikkaEverywherePrompt(int $priceSikka = 249): Prompt
{
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    return Prompt::factory()->sikkaPriced($priceSikka)->hasVersion()->create([
        'user_id' => $creator->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);
}

test('the navbar shows the Sikka wallet chip for signed-in users on both viewports', function () {
    sikkaEverywhereQuietEngagement();

    $buyer = sikkaEverywhereBuyer(142);

    $html = $this->actingAs($buyer)->get(route('home'))->assertOk()->getContent();

    // Desktop cluster + mobile top row — the chip renders twice, links to
    // the earnings ledger and carries the grouped integer.
    expect(substr_count($html, 'data-testid="nav-sikka-chip"'))->toBe(2)
        ->and($html)->toContain(route('dashboard.earnings'))
        ->and($html)->toContain('142')
        ->and($html)->not->toContain('Rs.');

    // A guest gets no wallet chip and no ledger link in the navbar.
    $this->app->make('auth')->guard('web')->forgetUser();

    $guest = $this->get(route('home'))->assertOk()->getContent();

    expect($guest)->not->toContain('data-testid="nav-sikka-chip"');
});

test('cards, the detail page and checkout price in Sikka only — no NPR anywhere', function () {
    sikkaEverywhereQuietEngagement();

    $prompt = sikkaEverywherePrompt(249);
    $product = Product::factory()->create(['prompt_id' => $prompt->id, 'price_paisa' => 24_900]);
    $buyer = User::factory()->create();

    // Card grid: the Sikka figure renders, the NPR mirror does not.
    $library = $this->get(route('library.index'))->assertOk()->getContent();

    expect($library)->toContain('249')
        ->and($library)->toContain('Sikka')
        ->and($library)->not->toContain('Rs. 249')
        ->and($library)->not->toContain('Rs.');

    // Detail page: Sikka license box, no NPR parenthetical.
    $detail = $this->get(route('prompts.show', $prompt))->assertOk()->getContent();

    expect($detail)->toContain('Sikka')
        ->and($detail)->toContain('249')
        ->and($detail)->not->toContain('Rs. 249')
        ->and($detail)->not->toContain('Rs.');

    // Checkout: the summary and the total are credits, and the Sikka rail
    // is the door (the buyer holds nothing yet, so the top-up CTA shows).
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create([
        'product_id' => $product->id, 'prompt_id' => $prompt->id,
        'price_paisa' => 24_900, 'currency' => 'npr', 'quantity' => 1,
    ]);

    $checkout = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();

    expect($checkout)->toContain('Sikka')
        ->and($checkout)->toContain('249')
        ->and($checkout)->toContain(route('sikka.topup'))
        ->and($checkout)->toContain('data-testid="nav-sikka-chip"')
        ->and($checkout)->not->toContain('Rs.');

    // Funded → the pay-with-credits door replaces the top-up CTA, still
    // with zero NPR on the page.
    sikkaEverywhereCredit($buyer, 300);

    $funded = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();

    expect($funded)->toContain(route('checkout.sikka.pay', $order))
        ->and($funded)->not->toContain('Rs.');
});

test('the earnings tab renders Sikka only — balances, ledger and withdrawals, no NPR', function () {
    sikkaEverywhereQuietEngagement();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    sikkaEverywhereCredit($creator, 320);

    $html = $this->actingAs($creator)->get(route('dashboard.earnings'))->assertOk()->getContent();

    expect($html)->toContain('Sikka spendable')
        ->and($html)->toContain('Cash-out eligible')
        ->and($html)->toContain('320')
        ->and($html)->toContain('Withdraw earnings')
        ->and($html)->toContain('Sikka credits ledger')
        // The unspeakable: the legacy NPR half is gone.
        ->and($html)->not->toContain('Request a payout')
        ->and($html)->not->toContain('Ledger balance')
        ->and($html)->not->toContain('Rs.');
});

test('the kill-switch is retired: the economy ships ON and the desk hides the toggle', function () {
    $settings = app(SettingsService::class);

    // Fresh install state: the constant AND the migrated row are ON.
    expect(SettingsService::DEFAULTS['sikka_enabled'])->toBe('1')
        ->and($settings->get('sikka_enabled'))->toBe('1')
        ->and($settings->isOn('sikka_enabled'))->toBeTrue();

    // The top-up storefront exists without anyone flipping anything.
    $this->get(route('sikka.topup'))->assertOk();

    // The desk no longer exposes the toggle; a rates save leaves the
    // retired setting untouched.
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $desk = $this->actingAs($admin)->get(route('admin.sikka.index'))->assertOk()->getContent();

    expect($desk)->not->toContain('name="sikka_enabled"')
        ->and($desk)->not->toContain('Kill-switch')
        ->and($desk)->toContain('kill-switch retired');

    $this->actingAs($admin)->put(route('admin.sikka.settings'), [
        'sikka_buy_paisa_per_token' => 100,
        'sikka_cashout_paisa_per_token' => 80,
        'engage_daily_sikka' => 1,
        'engage_publish_sikka' => 2,
        'engage_rating_sikka' => 1,
        'engage_daily_cap_sikka' => 5,
        'sikka_cashout_min' => 500,
    ])->assertRedirect();

    expect($settings->get('sikka_enabled'))->toBe('1');
});

/**
 * The display-only balance helper the navbar chip reads must equal the
 * locked balance the spending paths enforce — one ledger, one answer.
 */
test('the display balance equals the locked spendable balance', function () {
    sikkaEverywhereQuietEngagement();

    $buyer = sikkaEverywhereBuyer(120);
    sikkaEverywhereCredit($buyer, 80);

    $sikka = app(SikkaService::class);

    expect($sikka->spendableForDisplay($buyer))->toBe(200)
        ->and($sikka->spendableAvailable($buyer))->toBe(200);
});
