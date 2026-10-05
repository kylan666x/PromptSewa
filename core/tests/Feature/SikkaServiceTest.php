<?php

use App\Models\LicenseGrant;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\SikkaPack;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S2 (v1.8.0) — SikkaService, the single choke point.
 *
 * Locks: balance SUM semantics (holds inside the SUM, eligibility filter,
 * (int) on empty ledgers), the spend pipeline (shortfall 422 / success
 * atomic), the unlimited bypass (zero spend rows + grant source), top-up
 * double-approval idempotency, the served checkout rail, and the
 * single-controller-call-site arch. S7 extends the battery with property
 * and kill-switch tests.
 */

/** @return array{0: User, 1: Prompt, 2: Product} */
function sikkaSpendWorld(int $priceSikka): array
{
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->sikkaPriced($priceSikka)->create(['user_id' => $creator->id]);
    $product = Product::factory()->create([
        'prompt_id' => $prompt->id,
        'price_paisa' => $priceSikka * 100,
    ]);

    return [$creator, $prompt, $product];
}

function sikkaSpendCredit(
    User $user,
    int $amount,
    string $type = SikkaTransaction::TYPE_TOPUP,
    bool $eligible = true,
): SikkaTransaction {
    return SikkaTransaction::query()->create([
        'user_id' => $user->id,
        'type' => $type,
        'amount_sikka' => $amount,
        'cashout_eligible' => $eligible,
        'idempotency_key' => 'spendtest:'.uniqid('', true),
        'meta' => ['reason' => 'fixture'],
        'created_at' => now(),
    ]);
}

function sikkaSpendOrder(User $buyer, Product $product): Order
{
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'status' => Order::STATUS_PENDING,
        'currency' => Order::CURRENCY_NPR,
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'prompt_id' => $product->prompt_id,
        'price_paisa' => $product->price_paisa,
        'currency' => 'npr',
        'quantity' => 1,
    ]);

    return $order;
}

test('balances: spendable is the plain SUM (holds inside), cashoutable filters eligible rows only', function () {
    $user = User::factory()->create();
    sikkaSpendCredit($user, 10);
    sikkaSpendCredit($user, 5, SikkaTransaction::TYPE_ENGAGEMENT_REWARD, false);
    sikkaSpendCredit($user, -4, SikkaTransaction::TYPE_PAYOUT_HOLD, true);

    $service = app(SikkaService::class);

    expect($service->spendableAvailable($user))->toBe(11)   // 10 + 5 − 4, hold inside the SUM
        ->and($service->cashoutableAvailable($user))->toBe(6) // 10 − 4; engagement excluded
        ->and($service->spendableAvailable(User::factory()->create()))->toBe(0); // (int) on the empty SUM
});

test('spendSikka pays the order and moves spend + creator credit in one transaction', function () {
    [$creator, $prompt, $product] = sikkaSpendWorld(20);
    $buyer = User::factory()->create();
    sikkaSpendCredit($buyer, 50);

    $order = sikkaSpendOrder($buyer, $product);

    app(SikkaService::class)->spendSikka($order, $buyer);

    $order->refresh();
    $item = $order->items()->first();

    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->currency)->toBe(Order::CURRENCY_SIKKA)
        ->and($order->sikka_amount)->toBe(20)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment_method)->toBe('sikka')
        ->and($order->meta['buy_paisa_per_token'])->toBe(100); // snapshot at purchase

    $spend = SikkaTransaction::query()->where('idempotency_key', "spend:{$order->id}:{$item->id}")->sole();
    expect($spend->user_id)->toBe($buyer->id)
        ->and($spend->type)->toBe(SikkaTransaction::TYPE_SPEND)
        ->and($spend->amount_sikka)->toBe(-20)
        ->and($spend->cashout_eligible)->toBeTrue()
        ->and($spend->order_id)->toBe($order->id);

    $sale = SikkaTransaction::query()->where('idempotency_key', "sale:{$order->id}:{$item->id}")->sole();
    expect($sale->user_id)->toBe($creator->id)
        ->and($sale->type)->toBe(SikkaTransaction::TYPE_SALE_CREDIT)
        ->and($sale->amount_sikka)->toBe(20)
        ->and($sale->cashout_eligible)->toBeTrue();

    $grant = LicenseGrant::query()->where('user_id', $buyer->id)->where('prompt_id', $prompt->id)->sole();
    expect($grant->status)->toBe(LicenseGrant::STATUS_ACTIVE)
        ->and($grant->source)->toBeNull() // ordinary purchase path
        ->and($grant->order_item_id)->toBe($item->id);

    $service = app(SikkaService::class);
    expect($service->spendableAvailable($buyer))->toBe(30)
        ->and($service->cashoutableAvailable($creator))->toBe(20);
});

test('spendSikka shortfall throws and leaves no trace: no rows, no grants, order still pending', function () {
    [, $prompt, $product] = sikkaSpendWorld(20);
    $buyer = User::factory()->create();
    sikkaSpendCredit($buyer, 5);

    $order = sikkaSpendOrder($buyer, $product);

    expect(fn () => app(SikkaService::class)->spendSikka($order, $buyer))
        ->toThrow(InvalidArgumentException::class);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->currency)->toBe(Order::CURRENCY_NPR)
        ->and(SikkaTransaction::query()->where('order_id', $order->id)->count())->toBe(0)
        ->and(LicenseGrant::query()->where('user_id', $buyer->id)->where('prompt_id', $prompt->id)->count())->toBe(0)
        ->and(app(SikkaService::class)->spendableAvailable($buyer))->toBe(5);
});

test('checkout sikka rail: 422 on shortfall, paid redirect on success, 403 when the kill-switch is off', function () {
    [, , $product] = sikkaSpendWorld(20);
    $buyer = User::factory()->create();
    $order = sikkaSpendOrder($buyer, $product);

    app(SettingsService::class)->set('sikka_enabled', '1');

    // Shortfall → 422, nothing moved.
    $this->actingAs($buyer)->post(route('checkout.sikka.pay', $order))->assertStatus(422);
    expect($order->refresh()->status)->toBe(Order::STATUS_PENDING);

    // Funded → paid + redirect into the library.
    sikkaSpendCredit($buyer, 50);

    $this->actingAs($buyer)
        ->post(route('checkout.sikka.pay', $order))
        ->assertRedirect(route('purchases.index'));

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID);

    // Kill-switch off → the door refuses.
    app(SettingsService::class)->set('sikka_enabled', '0');

    $second = sikkaSpendOrder($buyer, $product);

    $this->actingAs($buyer)->post(route('checkout.sikka.pay', $second))->assertStatus(403);
});

test('checkout page serves the Sikka form when enabled + sufficient and hides it when disabled', function () {
    [, , $product] = sikkaSpendWorld(20);
    $buyer = User::factory()->create();
    sikkaSpendCredit($buyer, 50);
    $order = sikkaSpendOrder($buyer, $product);

    app(SettingsService::class)->set('sikka_enabled', '1');

    $this->actingAs($buyer)
        ->get(route('checkout.show', $order))
        ->assertOk()
        ->assertSee(route('checkout.sikka.pay', $order), false)
        ->assertSee('Sikka credits');

    // Insufficient: the rail shows the shortfall hint, the NPR rails stay.
    $poor = User::factory()->create();
    $poorOrder = sikkaSpendOrder($poor, $product);

    $this->actingAs($poor)
        ->get(route('checkout.show', $poorOrder))
        ->assertOk()
        ->assertSee('Sikka credits')
        ->assertDontSee(route('checkout.sikka.pay', $poorOrder), false);

    // Disabled: zero Sikka markup on the page.
    app(SettingsService::class)->set('sikka_enabled', '0');

    $this->actingAs($buyer)
        ->get(route('checkout.show', $order))
        ->assertOk()
        ->assertDontSee('Sikka credits')
        ->assertDontSee(route('checkout.sikka.pay', $order), false);
});

test('active unlimited membership bypasses the spend: zero rows, grant source membership_unlimited', function () {
    [, $prompt, $product] = sikkaSpendWorld(20);
    $buyer = User::factory()->create(); // zero balance — entitlement, not money

    $plan = MembershipPlan::factory()->unlimited()->create();
    Membership::factory()->create(['user_id' => $buyer->id, 'plan_id' => $plan->id]);

    $order = sikkaSpendOrder($buyer, $product);

    app(SikkaService::class)->spendSikka($order, $buyer);

    $order->refresh();

    expect($order->status)->toBe(Order::STATUS_PAID)
        ->and($order->currency)->toBe(Order::CURRENCY_SIKKA)
        ->and($order->sikka_amount)->toBe(0)
        ->and($order->meta['unlimited_membership_id'])->toBeInt()
        ->and(SikkaTransaction::query()->count())->toBe(0); // zero ledger rows, ever

    $grant = LicenseGrant::query()->where('user_id', $buyer->id)->where('prompt_id', $prompt->id)->sole();
    expect($grant->source)->toBe(LicenseGrant::SOURCE_MEMBERSHIP_UNLIMITED);
});

test('a lapsed or non-unlimited membership does not bypass the spend', function () {
    [, , $product] = sikkaSpendWorld(20);

    // Lapsed unlimited row: status active but ends_at in the past.
    $lapsed = User::factory()->create();
    $plan = MembershipPlan::factory()->unlimited()->create();
    Membership::factory()->lapsed()->create(['user_id' => $lapsed->id, 'plan_id' => $plan->id]);

    $lapsedOrder = sikkaSpendOrder($lapsed, $product);

    expect(fn () => app(SikkaService::class)->spendSikka($lapsedOrder, $lapsed))
        ->toThrow(InvalidArgumentException::class);

    // Active membership on a plan WITHOUT unlimited_unlock: pay normally.
    $member = User::factory()->create();
    Membership::factory()->create(['user_id' => $member->id]);
    sikkaSpendCredit($member, 50);

    $memberOrder = sikkaSpendOrder($member, $product);

    app(SikkaService::class)->spendSikka($memberOrder, $member);

    expect($memberOrder->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and(SikkaTransaction::query()
            ->where('order_id', $memberOrder->id)
            ->where('type', SikkaTransaction::TYPE_SPEND)
            ->count())->toBe(1);
});

test('approving a sikka-pack order credits topup + bonus exactly once', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();
    $pack = SikkaPack::factory()->create(['sikka_amount' => 100, 'bonus_sikka' => 10]);

    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create([
        'sikka_pack_id' => $pack->id,
        'price_paisa' => $pack->price_paisa,
        'currency' => 'npr',
        'quantity' => 1,
    ]);

    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect(); // double approval

    $rows = SikkaTransaction::query()->where('order_id', $order->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('type')->all())->toBe([SikkaTransaction::TYPE_TOPUP, SikkaTransaction::TYPE_TOPUP_BONUS])
        ->and($rows->pluck('amount_sikka')->all())->toBe([100, 10])
        ->and($rows->every(fn (SikkaTransaction $row) => $row->cashout_eligible === true))->toBeTrue()
        ->and($rows->every(fn (SikkaTransaction $row) => $row->user_id === $buyer->id))->toBeTrue();

    $service = app(SikkaService::class);

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and($service->spendableAvailable($buyer))->toBe(110)
        ->and($service->cashoutableAvailable($buyer))->toBe(110);
});

test('spendSikka has exactly one controller call site — the checkout Sikka rail', function () {
    $callSites = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(realpath(__DIR__.'/../../app/Http/Controllers'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        if (preg_match('/spendSikka\s*\(/', (string) file_get_contents($file->getPathname())) === 1) {
            $callSites[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    expect($callSites)->toHaveCount(1)
        ->and($callSites[0])->toContain('CheckoutController.php');
});
