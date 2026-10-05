<?php

use App\Models\Notification;
use App\Models\Payout;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SettingsService;
use App\Services\SikkaService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S4 (v1.8.0) — cash-out: Sikka → NPR at the spread.
 *
 * Locks: the request hold (row + ledger move in one transaction), the
 * minimum and over-cashoutable 422 refusals with zero trace, the
 * approve → settle machine storing settled_npr_paisa exactly once, the
 * reject/cancel release restoring the held credits, the notification
 * emissions, and the integer property of the settlement multiply across
 * 200 random amounts × 3 rates. Legacy NPR wallet payouts are asserted
 * untouched.
 */
function sikkaCashoutCredit(User $user, int $amount, bool $eligible = true): void
{
    SikkaTransaction::query()->create([
        'user_id' => $user->id,
        'type' => $eligible ? SikkaTransaction::TYPE_TOPUP : SikkaTransaction::TYPE_ENGAGEMENT_REWARD,
        'amount_sikka' => $amount,
        'cashout_eligible' => $eligible,
        'idempotency_key' => 'cashoutseed:'.uniqid('', true),
        'meta' => ['reason' => 'cash-out fixture'],
        'created_at' => now(),
    ]);
}

function sikkaCashoutRequest(User $user, int $amount): Payout
{
    $service = app(SikkaService::class);

    $payout = $service->requestPayout($user, $amount, Payout::METHOD_ESEWA_WALLET, '9800000000');

    expect($payout->status)->toBe(Payout::STATUS_REQUESTED);

    return $payout;
}

test('requesting a withdrawal parks a payout_hold row and lowers both balances', function () {
    app(SettingsService::class)->set('sikka_cashout_min', '500');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    sikkaCashoutCredit($creator, 1000);

    $service = app(SikkaService::class);
    expect($service->cashoutableAvailable($creator))->toBe(1000);

    $this->actingAs($creator)->post(route('dashboard.earnings.sikka.request'), [
        'amount_sikka' => 600,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination' => '9800000000',
    ])->assertRedirect();

    $payout = Payout::query()->where('user_id', $creator->id)->sole();

    expect($payout->status)->toBe(Payout::STATUS_REQUESTED)
        ->and($payout->source_currency)->toBe(Payout::SOURCE_SIKKA)
        ->and($payout->sikka_amount)->toBe(600)
        ->and($payout->amount_paisa)->toBe(0)
        ->and($payout->settled_npr_paisa)->toBeNull();

    $hold = SikkaTransaction::query()
        ->where('idempotency_key', "sikka_payout_hold:{$payout->id}")
        ->sole();

    expect($hold->type)->toBe(SikkaTransaction::TYPE_PAYOUT_HOLD)
        ->and($hold->amount_sikka)->toBe(-600)
        ->and($hold->cashout_eligible)->toBeTrue();

    expect($service->spendableAvailable($creator))->toBe(400) // hold lives inside the SUM
        ->and($service->cashoutableAvailable($creator))->toBe(400);
});

test('below-minimum and over-cashoutable requests are 422 with zero trace', function () {
    app(SettingsService::class)->set('sikka_cashout_min', '500');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    sikkaCashoutCredit($creator, 1000);

    // Below the minimum.
    $this->actingAs($creator)->post(route('dashboard.earnings.sikka.request'), [
        'amount_sikka' => 400,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination' => '9800000000',
    ])->assertStatus(422);

    // Over the cashoutable balance — even though the SPENDABLE balance is higher.
    sikkaCashoutCredit($creator, 500, eligible: false); // spend-only row does not count

    $this->actingAs($creator)->post(route('dashboard.earnings.sikka.request'), [
        'amount_sikka' => 1200,
        'method' => Payout::METHOD_ESEWA_WALLET,
        'destination' => '9800000000',
    ])->assertStatus(422);

    expect(Payout::query()->count())->toBe(0)
        ->and(SikkaTransaction::query()->where('type', SikkaTransaction::TYPE_PAYOUT_HOLD)->count())->toBe(0)
        ->and(app(SikkaService::class)->cashoutableAvailable($creator))->toBe(1000);
});

test('approve then settle stores the NPR amount once at the rate; reject and cancel release', function () {
    $settings = app(SettingsService::class);
    $settings->set('sikka_cashout_min', '100');
    $settings->set('sikka_buy_paisa_per_token', '100');
    $settings->set('sikka_cashout_paisa_per_token', '80');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    sikkaCashoutCredit($creator, 1000);

    $service = app(SikkaService::class);

    // Happy path: request → approve (no ledger row) → settle (NPR fixed once).
    $payout = sikkaCashoutRequest($creator, 600);
    $ledgerBefore = SikkaTransaction::query()->count();

    $this->actingAs($admin)->post(route('admin.finance.payouts.approve', $payout))->assertRedirect();
    expect($payout->refresh()->status)->toBe(Payout::STATUS_APPROVED)
        ->and(SikkaTransaction::query()->count())->toBe($ledgerBefore); // approve moves nothing

    $this->actingAs($admin)->post(route('admin.finance.payouts.settle', $payout))->assertRedirect();

    $payout->refresh();
    expect($payout->status)->toBe(Payout::STATUS_SETTLED)
        ->and($payout->settled_npr_paisa)->toBe(600 * 80)
        ->and($payout->settled_npr_paisa)->toBeInt()
        ->and(SikkaTransaction::query()->count())->toBe($ledgerBefore) // settle moves nothing
        ->and($service->cashoutableAvailable($creator))->toBe(400); // the hold stays the debit

    // Settling again never re-prices.
    $this->actingAs($admin)->post(route('admin.finance.payouts.settle', $payout))->assertStatus(422);

    expect($payout->refresh()->settled_npr_paisa)->toBe(48_000)
        ->and(Notification::query()->where('user_id', $creator->id)->where('type', Notification::TYPE_PAYOUT_SETTLED)->count())->toBe(1);

    // Reject: the release row restores the held credits.
    $rejected = sikkaCashoutRequest($creator, 300);
    $this->actingAs($admin)->post(route('admin.finance.payouts.reject', $rejected), ['note' => 'Bank details unmatched'])->assertRedirect();

    expect($rejected->refresh()->status)->toBe(Payout::STATUS_REJECTED);
    $release = SikkaTransaction::query()->where('idempotency_key', "sikka_payout_release:{$rejected->id}")->sole();
    expect($release->type)->toBe(SikkaTransaction::TYPE_PAYOUT_RELEASE)
        ->and($release->amount_sikka)->toBe(300)
        ->and($release->cashout_eligible)->toBeTrue();

    expect(Notification::query()->where('user_id', $creator->id)->where('type', Notification::TYPE_PAYOUT_REJECTED)->count())->toBe(1);

    // Cancel: the creator's own door releases too.
    $cancelled = sikkaCashoutRequest($creator, 200);
    $this->actingAs($creator)->post(route('dashboard.earnings.sikka.cancel', $cancelled))->assertRedirect();

    expect($cancelled->refresh()->status)->toBe(Payout::STATUS_CANCELLED)
        ->and(SikkaTransaction::query()->where('idempotency_key', "sikka_payout_release:{$cancelled->id}")->exists())->toBeTrue();

    // 1000 − 600 settled (the hold is the debit) = 400; the 300 and 200
    // holds were released back, so they net to zero against their holds.
    expect($service->cashoutableAvailable($creator))->toBe(400)
        ->and($service->spendableAvailable($creator))->toBe(400); // the settled hold is the only permanent debit
});

test('legacy NPR wallet payouts stay on the wallet rail', function () {
    $settings = app(SettingsService::class);
    $settings->set('payout_min_paisa', '10000');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 50_000,
        'idempotency_key' => 'adjustment:cashout-legacy',
        'meta' => ['reason' => 'legacy fixture'],
        'created_at' => now(),
    ]);

    $payout = app(WalletService::class)->requestPayout($creator, 20_000, Payout::METHOD_ESEWA_WALLET, '9800000000');

    expect($payout->isSikkaSource())->toBeFalse()
        ->and($payout->source_currency)->toBeNull()
        ->and($payout->sikka_amount)->toBeNull()
        ->and($payout->amount_paisa)->toBe(20_000)
        ->and(SikkaTransaction::query()->count())->toBe(0);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.finance.payouts.approve', $payout))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.finance.payouts.settle', $payout))->assertRedirect();

    expect($payout->refresh()->status)->toBe(Payout::STATUS_SETTLED)
        ->and($payout->settled_npr_paisa)->toBeNull()
        ->and(SikkaTransaction::query()->count())->toBe(0);
});

test('settled_npr_paisa is an exact integer multiple across 200 random amounts x 3 rates', function () {
    $settings = app(SettingsService::class);
    $settings->set('sikka_cashout_min', '1');
    $settings->set('sikka_buy_paisa_per_token', '100');

    $service = app(SikkaService::class);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    foreach ([50, 61, 80] as $rate) {
        $settings->set('sikka_cashout_paisa_per_token', (string) $rate);

        for ($i = 0; $i < 200; $i++) {
            $amount = random_int(1, 500_000);

            sikkaCashoutCredit($creator, $amount);

            $payout = $service->requestPayout($creator, $amount, Payout::METHOD_BANK, 'NP00-TEST-'.$i);
            $service->settlePayout(
                tap($payout, fn (Payout $p) => $p->transitionTo(Payout::STATUS_APPROVED)),
                $admin,
            );

            expect($payout->refresh()->settled_npr_paisa)
                ->toBe($amount * $rate)
                ->toBeInt();
        }
    }
});
