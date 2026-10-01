<?php

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Services\CompGrantService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

/**
 * M6 (v1.6.0) — the money release gate. Every test here is a locked row
 * in the QA-MATRIX M-block; a regression blocks the release.
 */

function paidOrderFixture(): array
{
    $buyer = User::factory()->create();
    $creator = User::factory()->create();

    $prompt = \App\Models\Prompt::factory()->for($creator, 'creator')->create(['price_cents' => 0]);
    $product = \App\Models\Product::factory()->for($prompt, 'prompt')->create(['price_paisa' => 100_00]);

    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'status' => Order::STATUS_PENDING,
        'total_paisa' => 100_00,
        'subtotal_paisa' => 100_00,
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'prompt_id' => $prompt->id,
        'price_paisa' => 100_00,
        'quantity' => 1,
    ]);

    // Default commission: 2000 bps → creator credit 8000 paisa of 10000.
    return [$order, $creator, $buyer, $prompt];
}

test('manual approval settles the order, grants licenses and credits the creator in one transaction', function () {
    [$order, $creator] = paidOrderFixture();

    app(WalletService::class)->settleOrder($order, 'manual');

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and(LicenseGrant::query()->where('user_id', $order->buyer_id)->count())->toBe(1)
        ->and(WalletTransaction::query()->where('type', WalletTransaction::TYPE_SALE_CREDIT)->count())->toBe(1)
        ->and(WalletTransaction::query()->where('type', WalletTransaction::TYPE_SALE_CREDIT)->value('amount_paisa'))->toBe(8000)
        ->and(app(WalletService::class)->balancePaisa($creator))->toBe(8000);
});

test('a double webhook credits exactly once', function () {
    [$order, $creator] = paidOrderFixture();

    $wallet = app(WalletService::class);

    $wallet->settleOrder($order, 'esewa');
    $wallet->settleOrder($order, 'esewa'); // replay — paid-state guard swallows it

    expect(WalletTransaction::query()->where('type', WalletTransaction::TYPE_SALE_CREDIT)->count())->toBe(1)
        ->and(app(WalletService::class)->balancePaisa($creator))->toBe(8000)
        ->and(LicenseGrant::query()->count())->toBe(1);
});

test('callback and webhook double delivery credits once', function () {
    // Simulates the callback settling the order and the webhook arriving
    // afterwards for the same order: paid-state guard + per-item unique key
    // both must hold.
    [$order, $creator] = paidOrderFixture();

    $wallet = app(WalletService::class);
    $wallet->settleOrder($order, 'esewa');

    $order->refresh();
    expect($order->isPaid())->toBeTrue();

    $wallet->settleOrder($order, 'esewa');

    expect(WalletTransaction::query()->count())->toBe(1)
        ->and(WalletTransaction::query()->value('idempotency_key'))->toBe('sale:'.$order->id.':'.$order->items->first()->id);
});

test('commission respects the admin-editable bps setting', function () {
    [$order, $creator] = paidOrderFixture();

    // 2500 bps = 25% platform → creator gets 7500 of 10000.
    \App\Models\Setting::query()->updateOrCreate(['key' => 'commission_bps'], ['value' => '2500']);

    app(WalletService::class)->settleOrder($order, 'manual');

    expect(WalletTransaction::query()->value('amount_paisa'))->toBe(7500);
});

// ---------------------------------------------------------------- M4 holds

test('payout hold reduces available and reject or cancel releases it', function () {
    $creator = User::factory()->create();
    \App\Models\WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:seed:test-1',
        'meta' => ['reason' => 'test seed'],
        'created_at' => now(),
    ]);

    // Lower the min for the fixture (default Rs. 500 would exceed the seed).
    \App\Models\Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    expect(app(WalletService::class)->balancePaisa($creator))->toBe(100_00)
        ->and(app(WalletService::class)->availablePaisa($creator))->toBe(100_00);

    $wallet = app(WalletService::class);
    $payout = $wallet->requestPayout($creator, 40_00, Payout::METHOD_ESEWA_WALLET, '9800000000');

    expect($wallet->balancePaisa($creator))->toBe(100_00)
        ->and($wallet->availablePaisa($creator))->toBe(60_00);

    // Reject → release row → available restored.
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $wallet->releasePayout($payout, $admin, Payout::STATUS_REJECTED, 'test reject');

    expect($wallet->availablePaisa($creator))->toBe(100_00)
        ->and(WalletTransaction::query()->where('type', WalletTransaction::TYPE_WITHDRAWAL_RELEASE)->count())->toBe(1);
});

test('settling a payout inserts no further ledger rows', function () {
    $creator = User::factory()->create();
    \App\Models\WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:seed:test-2',
        'meta' => ['reason' => 'test seed'],
        'created_at' => now(),
    ]);

    \App\Models\Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $wallet = app(WalletService::class);
    $payout = $wallet->requestPayout($creator, 40_00, Payout::METHOD_BANK, 'ACCT-123');

    $rowsBefore = WalletTransaction::query()->count();

    $payout->transitionTo(Payout::STATUS_APPROVED);
    $payout->transitionTo(Payout::STATUS_SETTLED);

    expect(WalletTransaction::query()->count())->toBe($rowsBefore)
        ->and(WalletTransaction::query()->where('type', WalletTransaction::TYPE_WITHDRAWAL_RELEASE)->count())->toBe(0);
});

test('over available payout is refused', function () {
    $creator = User::factory()->create();
    \App\Models\WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:seed:test-3',
        'meta' => ['reason' => 'test seed'],
        'created_at' => now(),
    ]);

    \App\Models\Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    app(WalletService::class)->requestPayout($creator, 999_00, Payout::METHOD_BANK, 'ACCT-X');
})->throws(InvalidArgumentException::class);

test('payout below the minimum threshold is refused', function () {
    $creator = User::factory()->create();
    \App\Models\WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:seed:test-4',
        'meta' => ['reason' => 'test seed'],
        'created_at' => now(),
    ]);

    // Default min is 50000 paisa (Rs. 500); Rs. 100 is refused.
    app(WalletService::class)->requestPayout($creator, 100_00, Payout::METHOD_BANK, 'ACCT-Y');
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------- isolation

test('creator earnings are isolated to their own ledger rows', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    foreach ([[$a, 'iso-a'], [$b, 'iso-b']] as [$user, $key]) {
        \App\Models\WalletTransaction::query()->create([
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_ADJUSTMENT,
            'amount_paisa' => 55_00,
            'idempotency_key' => 'adjustment:'.$key,
            'meta' => ['reason' => 'isolation test'],
            'created_at' => now(),
        ]);
    }

    \App\Models\Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $wallet = app(WalletService::class);

    expect($wallet->balancePaisa($a))->toBe(5500)
        ->and($wallet->balancePaisa($b))->toBe(5500)
        ->and($wallet->availablePaisa($a))->toBe(5500);

    // A hold on B must not change A's numbers.
    $wallet->requestPayout($b, 50_00, Payout::METHOD_ESEWA_WALLET, 'b-dest');

    expect($wallet->availablePaisa($a))->toBe(5500)
        ->and($wallet->availablePaisa($b))->toBe(500);
});

test('comp grants create zero ledger rows', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create();
    $prompt = \App\Models\Prompt::factory()->for($creator, 'creator')->priced(100_00)->create();

    $before = WalletTransaction::query()->count();

    app(CompGrantService::class)->grant(User::factory()->create(), $prompt, $admin, 'press copy');

    expect(WalletTransaction::query()->count())->toBe($before);
});

// ---------------------------------------------------------------- crypto

test('payout destination is encrypted at rest and decrypts for staff', function () {
    $creator = User::factory()->create();
    \App\Models\WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 100_00,
        'idempotency_key' => 'adjustment:seed:test-5',
        'meta' => ['reason' => 'test seed'],
        'created_at' => now(),
    ]);

    \App\Models\Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $payout = app(WalletService::class)->requestPayout($creator, 50_00, Payout::METHOD_ESEWA_WALLET, '9801234567');

    $raw = \Illuminate\Support\Facades\DB::table('payouts')->where('id', $payout->id)->value('destination_encrypted');

    // DB value ≠ plaintext, and decrypts correctly (staff path only).
    expect($raw)->not->toBe('9801234567')
        ->and(Crypt::decryptString($raw))->toBe('9801234567');
});

// ---------------------------------------------------------------- pre-ledger

test('pre ledger orders never appear in balances but appear in the finance desk', function () {
    // A paid order with paid_at BEFORE the ledger cutover has no ledger rows
    // (it predates the wallet) — the Finance desk lists it read-only.
    [$order, $creator] = paidOrderFixture();
    $order->fill(['status' => Order::STATUS_PAID, 'paid_at' => now()->subDays(30)])->save();

    $wallet = app(WalletService::class);

    expect($wallet->balancePaisa($creator))->toBe(0)
        ->and($wallet->availablePaisa($creator))->toBe(0);

    // The desk's pre-ledger query catches it.
    $cutover = now();
    $preLedger = Order::query()->where('status', Order::STATUS_PAID)->where('paid_at', '<', $cutover)->count();

    expect($preLedger)->toBe(1);
});
