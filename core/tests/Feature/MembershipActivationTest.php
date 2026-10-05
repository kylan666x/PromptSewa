<?php

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserFrameUnlock;
use App\Services\CheckoutService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S5 (v1.8.0) — memberships ride the NPR rails; the paid transition
 * activates them.
 *
 * Locks: the storefront + buy form (one pending NPR order), activation
 * through the paid transition (membership row + first stipend + badge/
 * frame/verified perks, each idempotent — a double approval grants
 * exactly once), the same path for eSewa settlement, and the notification
 * emissions (stipend, frame, verified, badge).
 */

/** @return array{0: MembershipPlan, 1: Badge, 2: Frame} */
function membershipPlanWorld(): array
{
    $badge = Badge::query()->create([
        'name' => 'Patron badge',
        'slug' => 'patron-badge',
        'description' => 'Membership perk',
        'criterion' => 'manual',
        'is_active' => true,
    ]);

    $frame = Frame::query()->create([
        'name' => 'Patron ring',
        'image_path' => 'frames/patron-ring.png',
        'is_active' => true,
    ]);

    $plan = MembershipPlan::factory()->create([
        'name' => 'Patron plan',
        'slug' => 'patron-plan',
        'duration_days' => 60,
        'price_paisa' => 99_900,
        'stipend_sikka' => 15,
        'perks' => [
            MembershipPlan::PERK_UNLIMITED_UNLOCK => true,
            MembershipPlan::PERK_BADGE_ID => $badge->id,
            MembershipPlan::PERK_FRAME_ID => $frame->id,
            MembershipPlan::PERK_GRANT_VERIFIED => true,
        ],
    ]);

    return [$plan, $badge, $frame];
}

test('the storefront lists plans and the buy form creates a pending NPR order', function () {
    [$plan] = membershipPlanWorld();

    $this->get(route('memberships.index'))
        ->assertOk()
        ->assertSee('Patron plan')
        ->assertSee('Sign in to join');

    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->post(route('checkout.memberships.buy', $plan))
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->sole();

    expect($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->currency)->toBe(Order::CURRENCY_NPR)
        ->and($order->total_paisa)->toBe(99_900)
        ->and($order->items()->sole()->membership_plan_id)->toBe($plan->id)
        ->and(Membership::query()->count())->toBe(0); // grants follow payment
});

test('approval activates exactly once: membership row, first stipend and the perks', function () {
    [$plan, $badge, $frame] = membershipPlanWorld();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $buyer = User::factory()->create();

    $order = app(CheckoutService::class)->createMembershipOrder($buyer, $plan)['order'];

    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();

    $membership = Membership::query()->where('user_id', $buyer->id)->sole();

    expect($membership->plan_id)->toBe($plan->id)
        ->and($membership->status)->toBe(Membership::STATUS_ACTIVE)
        ->and($membership->source_order_id)->toBe($order->id)
        ->and((int) round($membership->starts_at->diffInDays($membership->ends_at)))->toBe(60);

    $stipend = SikkaTransaction::query()
        ->where('type', SikkaTransaction::TYPE_MEMBERSHIP_STIPEND)
        ->sole();

    expect($stipend->user_id)->toBe($buyer->id)
        ->and($stipend->amount_sikka)->toBe(15)
        ->and($stipend->cashout_eligible)->toBeTrue()
        ->and($stipend->idempotency_key)->toBe("stipend:{$membership->id}:0");

    expect(UserBadge::query()->where('user_id', $buyer->id)->where('badge_id', $badge->id)->sole()->reason)
        ->toBe('membership:patron-plan');

    expect(UserFrameUnlock::query()->where('user_id', $buyer->id)->where('frame_id', $frame->id)->sole()->source)
        ->toBe(UserFrameUnlock::SOURCE_MEMBERSHIP);

    expect($buyer->refresh()->is_verified)->toBeTrue();

    // The bell rows ride the same transaction.
    expect(Notification::query()->where('user_id', $buyer->id)->where('type', Notification::TYPE_SIKKA_STIPEND)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $buyer->id)->where('type', Notification::TYPE_BADGE_AWARDED)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $buyer->id)->where('type', Notification::TYPE_FRAME_UNLOCKED)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $buyer->id)->where('type', Notification::TYPE_VERIFIED_GRANTED)->count())->toBe(1);

    // Double approval (a replay, a second admin click): credit once.
    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();

    expect(Membership::query()->count())->toBe(1)
        ->and(SikkaTransaction::query()->where('type', SikkaTransaction::TYPE_MEMBERSHIP_STIPEND)->count())->toBe(1)
        ->and(UserBadge::query()->count())->toBe(1)
        ->and(UserFrameUnlock::query()->count())->toBe(1)
        // stipend + badge + frame + verified + the order-approved bell row.
        ->and(Notification::query()->where('user_id', $buyer->id)->count())->toBe(5);
});

test('eSewa settlement rides the same one activation path', function () {
    [$plan] = membershipPlanWorld();

    $buyer = User::factory()->create();
    $order = app(CheckoutService::class)->createMembershipOrder($buyer, $plan)['order'];

    app(WalletService::class)->settleOrder($order, 'esewa');

    expect($order->refresh()->status)->toBe(Order::STATUS_PAID)
        ->and(Membership::query()->where('user_id', $buyer->id)->count())->toBe(1)
        ->and(SikkaTransaction::query()->where('type', SikkaTransaction::TYPE_MEMBERSHIP_STIPEND)->count())->toBe(1);
});
