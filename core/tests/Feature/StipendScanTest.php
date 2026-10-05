<?php

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S3 (v1.8.0) — pv:stipend-scan: elapsed periods grant exactly once,
 * replay-safe forever; lapsed memberships flip to expired (state change
 * on the membership row only) and stop.
 */
test('the scan grants every elapsed period once and a replay grants nothing', function () {
    $plan = MembershipPlan::factory()->create(['duration_days' => 90, 'stipend_sikka' => 10]);
    $user = User::factory()->create();

    // 61 days in: periods 0, 1 and 2 have elapsed (period 0 = activation).
    $membership = Membership::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(61),
        'ends_at' => now()->addDays(29),
        'status' => Membership::STATUS_ACTIVE,
    ]);

    $this->artisan('pv:stipend-scan')->assertSuccessful();

    $rows = SikkaTransaction::query()
        ->where('user_id', $user->id)
        ->where('type', SikkaTransaction::TYPE_MEMBERSHIP_STIPEND)
        ->orderBy('id')
        ->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('amount_sikka')->all())->toBe([10, 10, 10])
        ->and($rows->every(fn (SikkaTransaction $row) => $row->cashout_eligible === true))->toBeTrue()
        ->and($rows->pluck('idempotency_key')->all())->toBe([
            "stipend:{$membership->id}:0",
            "stipend:{$membership->id}:1",
            "stipend:{$membership->id}:2",
        ]);

    // Replay — a doubled cron, a manual rerun — grants nothing.
    $this->artisan('pv:stipend-scan')->assertSuccessful();

    expect(SikkaTransaction::query()->where('user_id', $user->id)->count())->toBe(3);

    $sikka = app(SikkaService::class);
    expect($sikka->spendableAvailable($user))->toBe(30)
        ->and($sikka->cashoutableAvailable($user))->toBe(30);
});

test('a lapsed membership gets its final period, flips to expired, and stops', function () {
    $plan = MembershipPlan::factory()->create(['duration_days' => 30, 'stipend_sikka' => 5]);
    $user = User::factory()->create();

    $membership = Membership::factory()->lapsed()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
    ]);

    $this->artisan('pv:stipend-scan')->assertSuccessful();

    expect($membership->refresh()->status)->toBe(Membership::STATUS_EXPIRED)
        ->and(SikkaTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', SikkaTransaction::TYPE_MEMBERSHIP_STIPEND)
            ->count())->toBe(1);

    // Expired memberships stop: a second scan grants nothing more.
    $this->artisan('pv:stipend-scan')->assertSuccessful();

    expect(SikkaTransaction::query()->where('user_id', $user->id)->count())->toBe(1);

    $sikka = app(SikkaService::class);
    expect($sikka->spendableAvailable($user))->toBe(5)
        ->and($sikka->cashoutableAvailable($user))->toBe(5);
});

test('stipend-less plans write no rows and the scan never breaks', function () {
    $plan = MembershipPlan::factory()->create(['duration_days' => 30, 'stipend_sikka' => 0]);
    $user = User::factory()->create();

    Membership::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'starts_at' => now()->subDays(61),
        'ends_at' => now()->addDays(29),
    ]);

    $this->artisan('pv:stipend-scan')->assertSuccessful();

    expect(SikkaTransaction::query()->count())->toBe(0);
});
