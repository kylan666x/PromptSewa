<?php

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\SikkaPack;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * S6/S7 (v1.8.0) — the Admin → Sikka desk.
 *
 * Locks: the pill (same commit, §6.36), moderator 403 on the page AND the
 * writes, the documented bounds (buy 50–500, cash-out 10…buy, engagement
 * amounts + cap, cash-out minimum), packs/plans CRUD (sold rows
 * deactivate instead of deleting), the ledger browser filters, and the
 * admin grant with the per-grant eligibility flip — spend-only by
 * default, flipped only with the mandatory reason audited in meta AND the
 * admin log. Eligibility is decided at WRITE TIME: the insert-only ledger
 * has no UPDATE path (SikkaLedgerImmutabilityTest owns that ban).
 */
function sikkaDeskAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

test('the desk renders for admins with its pill and 403s moderators everywhere', function () {
    $admin = sikkaDeskAdmin();
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $html = $this->actingAs($admin)->get(route('admin.sikka.index'))->assertOk()->getContent();

    expect($html)->toContain('Sikka desk')
        ->and($html)->toContain(route('admin.sikka.index'));

    // Moderators: the page, the settings save and the CRUD all refuse.
    $this->actingAs($moderator)->get(route('admin.sikka.index'))->assertStatus(403);

    $this->actingAs($moderator)->put(route('admin.sikka.settings'), [
        'sikka_buy_paisa_per_token' => 100,
        'sikka_cashout_paisa_per_token' => 80,
        'engage_daily_sikka' => 1,
        'engage_publish_sikka' => 2,
        'engage_rating_sikka' => 1,
        'engage_daily_cap_sikka' => 5,
        'sikka_cashout_min' => 500,
    ])->assertStatus(403);

    $this->actingAs($moderator)->post(route('admin.sikka.grants.store'), [
        'user_id' => $admin->id,
        'amount_sikka' => 10,
        'reason' => 'moderator attempt',
    ])->assertStatus(403);
});

test('settings respect the documented bounds and persist valid values', function () {
    $admin = sikkaDeskAdmin();

    $valid = [
        'sikka_buy_paisa_per_token' => 100,
        'sikka_cashout_paisa_per_token' => 80,
        'engage_daily_sikka' => 1,
        'engage_publish_sikka' => 2,
        'engage_rating_sikka' => 1,
        'engage_daily_cap_sikka' => 5,
        'sikka_cashout_min' => 500,
    ];

    // Buy below 50 / above 500.
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['sikka_buy_paisa_per_token' => 40]))->assertSessionHasErrors('sikka_buy_paisa_per_token');
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['sikka_buy_paisa_per_token' => 501]))->assertSessionHasErrors('sikka_buy_paisa_per_token');

    // Cash-out below 10 / above the buy rate.
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['sikka_cashout_paisa_per_token' => 9]))->assertSessionHasErrors('sikka_cashout_paisa_per_token');
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['sikka_cashout_paisa_per_token' => 101]))->assertSessionHasErrors('sikka_cashout_paisa_per_token');

    // Engagement amounts and the cap are bounded too.
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['engage_publish_sikka' => 51]))->assertSessionHasErrors('engage_publish_sikka');
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['engage_daily_cap_sikka' => 501]))->assertSessionHasErrors('engage_daily_cap_sikka');

    // A valid save persists (rates + engagement bounds).
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, [
        'sikka_buy_paisa_per_token' => 200,
        'sikka_cashout_paisa_per_token' => 150,
    ]))->assertRedirect();

    $settings = app(SettingsService::class);

    expect($settings->get('sikka_buy_paisa_per_token'))->toBe('200')
        ->and($settings->get('sikka_cashout_paisa_per_token'))->toBe('150')
        ->and(app(SikkaService::class)->cashoutRatePaisaPerToken())->toBe(150);

    // S1 (v1.9.0): the kill-switch is RETIRED — a stale "0" smuggled into
    // the payload is ignored, the economy stays ON, and nothing in the desk
    // can turn it off again.
    $this->actingAs($admin)->put(route('admin.sikka.settings'), array_merge($valid, ['sikka_enabled' => '0']))->assertRedirect();

    expect($settings->isOn('sikka_enabled'))->toBeTrue();
});

test('packs CRUD round-trips and a sold pack deactivates instead of deleting', function () {
    $admin = sikkaDeskAdmin();

    $this->actingAs($admin)->post(route('admin.sikka.packs.store'), [
        'name' => 'Desk Pack',
        'slug' => 'desk-pack',
        'sikka_amount' => 250,
        'bonus_sikka' => 25,
        'price_paisa' => 20_000,
    ])->assertRedirect();

    $pack = SikkaPack::query()->where('slug', 'desk-pack')->sole();
    expect($pack->sikka_amount)->toBe(250)->and($pack->bonus_sikka)->toBe(25)->and($pack->active)->toBeTrue();

    // Duplicate slug refused.
    $this->actingAs($admin)->post(route('admin.sikka.packs.store'), [
        'name' => 'Dupe', 'slug' => 'desk-pack', 'sikka_amount' => 1, 'bonus_sikka' => 0, 'price_paisa' => 100,
    ])->assertSessionHasErrors('slug');

    $this->actingAs($admin)->put(route('admin.sikka.packs.update', $pack), [
        'name' => 'Desk Pack v2',
        'slug' => 'desk-pack',
        'sikka_amount' => 300,
        'bonus_sikka' => 0,
        'price_paisa' => 24_000,
        'active' => '1',
    ])->assertRedirect();

    expect($pack->refresh()->sikka_amount)->toBe(300)->and($pack->name)->toBe('Desk Pack v2');

    // Unreferenced → hard delete.
    $this->actingAs($admin)->delete(route('admin.sikka.packs.destroy', $pack))->assertRedirect();
    expect(SikkaPack::query()->whereKey($pack->id)->exists())->toBeFalse();

    // Referenced by an order line → deactivate, never delete.
    $sold = SikkaPack::query()->create([
        'name' => 'Sold Pack', 'slug' => 'sold-pack', 'sikka_amount' => 100,
        'bonus_sikka' => 0, 'price_paisa' => 10_000, 'active' => true,
    ]);
    $buyer = User::factory()->create();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create(['sikka_pack_id' => $sold->id, 'price_paisa' => 10_000, 'currency' => 'npr', 'quantity' => 1]);

    $this->actingAs($admin)->delete(route('admin.sikka.packs.destroy', $sold))->assertRedirect();

    expect($sold->refresh()->active)->toBeFalse()
        ->and(SikkaPack::query()->whereKey($sold->id)->exists())->toBeTrue();
});

test('plans CRUD round-trips the perks picker and referenced plans deactivate', function () {
    $admin = sikkaDeskAdmin();

    $badge = Badge::query()->create(['name' => 'Desk badge', 'slug' => 'desk-badge', 'criterion' => 'manual', 'is_active' => true]);
    $frame = Frame::query()->create(['name' => 'Desk ring', 'image_path' => 'frames/desk-ring.png', 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.sikka.plans.store'), [
        'name' => 'Desk Plan',
        'slug' => 'desk-plan',
        'duration_days' => 90,
        'price_paisa' => 150_000,
        'stipend_sikka' => 20,
        'badge_id' => $badge->id,
        'frame_id' => $frame->id,
        'unlimited_unlock' => '1',
        'grant_verified' => '1',
        'active' => '1',
    ])->assertRedirect();

    $plan = MembershipPlan::query()->where('slug', 'desk-plan')->sole();

    expect($plan->duration_days)->toBe(90)
        ->and($plan->stipend_sikka)->toBe(20)
        ->and($plan->hasUnlimitedUnlock())->toBeTrue()
        ->and($plan->perk(MembershipPlan::PERK_BADGE_ID))->toBe($badge->id)
        ->and($plan->perk(MembershipPlan::PERK_FRAME_ID))->toBe($frame->id)
        ->and($plan->perk(MembershipPlan::PERK_GRANT_VERIFIED))->toBeTrue();

    // Bounds: duration and stipend are guarded.
    $this->actingAs($admin)->post(route('admin.sikka.plans.store'), [
        'name' => 'Bad Plan', 'slug' => 'bad-plan', 'duration_days' => 0, 'price_paisa' => 100,
        'stipend_sikka' => 1,
    ])->assertSessionHasErrors('duration_days');

    $this->actingAs($admin)->put(route('admin.sikka.plans.update', $plan), [
        'name' => 'Desk Plan v2',
        'slug' => 'desk-plan',
        'duration_days' => 30,
        'price_paisa' => 50_000,
        'stipend_sikka' => 0,
        'active' => '1',
    ])->assertRedirect();

    expect($plan->refresh()->name)->toBe('Desk Plan v2')
        ->and($plan->stipend_sikka)->toBe(0)
        ->and($plan->hasUnlimitedUnlock())->toBeFalse();

    // Unreferenced → hard delete.
    $this->actingAs($admin)->delete(route('admin.sikka.plans.destroy', $plan))->assertRedirect();
    expect(MembershipPlan::query()->whereKey($plan->id)->exists())->toBeFalse();

    // Referenced by a membership → deactivate.
    $held = MembershipPlan::factory()->create(['name' => 'Held Plan']);
    Membership::factory()->create(['plan_id' => $held->id]);

    $this->actingAs($admin)->delete(route('admin.sikka.plans.destroy', $held))->assertRedirect();

    expect($held->refresh()->active)->toBeFalse()
        ->and(MembershipPlan::query()->whereKey($held->id)->exists())->toBeTrue();
});

test('admin grants are spend-only by default; the flip is audited in meta and the admin log', function () {
    $admin = sikkaDeskAdmin();
    $member = User::factory()->create();

    Log::spy();

    // Default: spend-only.
    $this->actingAs($admin)->post(route('admin.sikka.grants.store'), [
        'user_id' => $member->id,
        'amount_sikka' => 100,
        'reason' => 'Support make-good',
    ])->assertRedirect();

    $spendOnly = SikkaTransaction::query()->where('user_id', $member->id)->sole();

    expect($spendOnly->type)->toBe(SikkaTransaction::TYPE_ADMIN_GRANT)
        ->and($spendOnly->cashout_eligible)->toBeFalse()
        ->and($spendOnly->meta['reason'])->toBe('Support make-good')
        ->and($spendOnly->meta['granted_by'])->toBe($admin->id)
        ->and($spendOnly->meta['eligibility_flip'])->toBeFalse();

    $sikka = app(SikkaService::class);

    expect($sikka->spendableAvailable($member))->toBe(100)
        ->and($sikka->cashoutableAvailable($member))->toBe(0);

    // The flip: cash-out eligible, still with the mandatory reason.
    $this->actingAs($admin)->post(route('admin.sikka.grants.store'), [
        'user_id' => $member->id,
        'amount_sikka' => 50,
        'reason' => 'Approved cash-out exception',
        'cashout_eligible' => '1',
    ])->assertRedirect();

    $flipped = SikkaTransaction::query()
        ->where('user_id', $member->id)
        ->where('cashout_eligible', true)
        ->sole();

    expect($flipped->amount_sikka)->toBe(50)
        ->and($flipped->meta['reason'])->toBe('Approved cash-out exception')
        ->and($flipped->meta['eligibility_flip'])->toBeTrue()
        ->and($sikka->cashoutableAvailable($member))->toBe(50);

    // The mandatory reason is enforced.
    $this->actingAs($admin)->post(route('admin.sikka.grants.store'), [
        'user_id' => $member->id,
        'amount_sikka' => 10,
        'reason' => 'x',
    ])->assertSessionHasErrors('reason');

    // Both grants were written to the admin log.
    Log::shouldHaveReceived('info')->with('sikka.admin_grant', Mockery::type('array'))->twice();
});

test('the ledger browser filters by user, type and eligibility', function () {
    $admin = sikkaDeskAdmin();
    $user = User::factory()->create();

    SikkaTransaction::query()->create([
        'user_id' => $user->id, 'type' => SikkaTransaction::TYPE_TOPUP, 'amount_sikka' => 12_345,
        'cashout_eligible' => true, 'idempotency_key' => 'desk:topup', 'meta' => [], 'created_at' => now(),
    ]);
    SikkaTransaction::query()->create([
        'user_id' => $user->id, 'type' => SikkaTransaction::TYPE_ENGAGEMENT_REWARD, 'amount_sikka' => 7,
        'cashout_eligible' => false, 'idempotency_key' => 'desk:engage', 'meta' => [], 'created_at' => now(),
    ]);

    // Assertions read the TABLE only — the economy stats above it are
    // global sums and would leak amounts across filtered views.
    $tableOf = function (string $html): string {
        $start = strpos($html, '<tbody');
        $end = strpos($html, '</tbody>');

        return ($start !== false && $end !== false) ? substr($html, $start, $end - $start) : '';
    };

    $html = $this->actingAs($admin)->get(route('admin.sikka.index'))->assertOk()->getContent();
    expect($tableOf($html))->toContain('12,345')->and($tableOf($html))->toContain('engagement reward');

    // Eligibility filter: cash-out rows only.
    $cashout = $this->actingAs($admin)->get(route('admin.sikka.index', ['eligibility' => 'cashout']))->assertOk()->getContent();
    expect($tableOf($cashout))->toContain('12,345')->and($tableOf($cashout))->not->toContain('engagement reward');

    // Type filter: engagement rows only.
    $engage = $this->actingAs($admin)->get(route('admin.sikka.index', ['type' => 'engagement_reward']))->assertOk()->getContent();
    expect($tableOf($engage))->toContain('engagement reward')->and($tableOf($engage))->not->toContain('12,345');
});
