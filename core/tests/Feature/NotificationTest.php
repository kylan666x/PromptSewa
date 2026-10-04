<?php

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * F6 (v1.7.8) — live notifications v1.
 *
 * cPanel has no websockets: the bell polls a small JSON endpoint every 60
 * seconds (auth + throttle:30,1) and renders the newest 20. Rows are
 * written ONLY by Notification::emit, inside the emitting event's own
 * transaction — the emitter matrix below is the contract.
 */

/** Pending manual order with one real product line (approval settles it). */
function notificationOrderFixture(): array
{
    $buyer = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->create(['price_cents' => 0]);
    $product = Product::factory()->for($prompt, 'prompt')->create(['price_paisa' => 100_00]);

    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'status' => Order::STATUS_PENDING,
        'payment_method' => 'manual',
        'total_paisa' => 100_00,
        'subtotal_paisa' => 100_00,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'prompt_id' => $prompt->id,
        'price_paisa' => 100_00,
        'quantity' => 1,
    ]);

    return [$order, $creator, $buyer];
}

// ---------------------------------------------------------------------------
// Emitter matrix — each event writes exactly one row, for the right person
// ---------------------------------------------------------------------------

test('order approval writes exactly one bell row for the buyer', function () {
    [$order] = notificationOrderFixture();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->patch(route('admin.orders.approve', $order))->assertRedirect();

    $rows = Notification::query()->where('user_id', $order->buyer_id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->type)->toBe(Notification::TYPE_ORDER_APPROVED)
        ->and($rows->first()->subject_type)->toBe(Order::class)
        ->and($rows->first()->subject_id)->toBe($order->id)
        ->and($rows->first()->read_at)->toBeNull();
});

test('order rejection writes exactly one bell row for the buyer', function () {
    [$order] = notificationOrderFixture();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->patch(route('admin.orders.reject', $order))->assertRedirect();

    $rows = Notification::query()->where('user_id', $order->buyer_id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->type)->toBe(Notification::TYPE_ORDER_REJECTED);
});

test('verified grant and revoke each write exactly one bell row', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.users.verified', $member))->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.users.verified', $member))->assertRedirect();

    $rows = Notification::query()->where('user_id', $member->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->type)->toBe(Notification::TYPE_VERIFIED_GRANTED)
        ->and($rows[1]->type)->toBe(Notification::TYPE_VERIFIED_REVOKED);
});

test('a badge award writes exactly one bell row and a replay writes none', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();

    $badge = Badge::query()->create([
        'name' => 'Verified', 'slug' => 'verified',
        'criterion' => 'verified', 'is_active' => true,
    ]);

    foreach (['Community spotlight pick', 'Trying again'] as $reason) {
        $this->actingAs($admin)->post(route('admin.badges.award'), [
            'badge_id' => $badge->id,
            'user_id' => $user->id,
            'reason' => $reason,
        ])->assertRedirect();
    }

    $rows = Notification::query()->where('user_id', $user->id)->get();

    expect($rows)->toHaveCount(1) // the duplicate award is refused
        ->and($rows->first()->type)->toBe(Notification::TYPE_BADGE_AWARDED);
});

test('a frame unlock writes exactly one bell row and a replay writes none', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();

    $frame = Frame::query()->create([
        'name' => 'Founder Frame', 'image_path' => 'frames/founder.png',
        'is_active' => true, 'criterion' => 'manual',
    ]);

    foreach (['Founder drop', 'Second try'] as $reason) {
        $this->actingAs($admin)->post(route('admin.frames.award'), [
            'user_id' => $user->id,
            'frame_id' => $frame->id,
            'reason' => $reason,
        ])->assertRedirect();
    }

    $rows = Notification::query()->where('user_id', $user->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->type)->toBe(Notification::TYPE_FRAME_UNLOCKED);
});

test('payout settle and reject each write exactly one bell row for the creator', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    WalletTransaction::query()->create([
        'user_id' => $creator->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 200_00,
        'idempotency_key' => 'adjustment:notification-test',
        'meta' => ['reason' => 'F6 seed'],
        'created_at' => now(),
    ]);
    Setting::query()->updateOrCreate(['key' => 'payout_min_paisa'], ['value' => '2000']);

    $wallet = app(WalletService::class);
    $toSettle = $wallet->requestPayout($creator, 40_00, Payout::METHOD_ESEWA_WALLET, '9800000000');
    $toReject = $wallet->requestPayout($creator, 40_00, Payout::METHOD_ESEWA_WALLET, '9800000000');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->post(route('admin.finance.payouts.approve', $toSettle))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.finance.payouts.settle', $toSettle))->assertRedirect();
    $this->actingAs($admin)->post(route('admin.finance.payouts.reject', $toReject), ['note' => 'Bad destination'])->assertRedirect();

    $rows = Notification::query()->where('user_id', $creator->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->where('type', Notification::TYPE_PAYOUT_SETTLED)->count())->toBe(1)
        ->and($rows->where('type', Notification::TYPE_PAYOUT_REJECTED)->count())->toBe(1);
});

test('report triage writes exactly one bell row per decision for the reporter', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $reporter = User::factory()->create();

    $resolved = PromptReport::factory()->create(['user_id' => $reporter->id]);
    $dismissed = PromptReport::factory()->create(['user_id' => $reporter->id]);

    $this->actingAs($admin)->patch(route('admin.reports.status', $resolved), ['status' => PromptReport::STATUS_RESOLVED])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.reports.status', $dismissed), ['status' => PromptReport::STATUS_DISMISSED])->assertRedirect();

    $rows = Notification::query()->where('user_id', $reporter->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->where('type', Notification::TYPE_REPORT_RESOLVED)->count())->toBe(1)
        ->and($rows->where('type', Notification::TYPE_REPORT_DISMISSED)->count())->toBe(1);
});

test('a comp grant writes exactly one bell row and a replay writes none', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create(['title' => 'F6 Press Copy']);

    foreach (['Press copy', 'Trying again'] as $reason) {
        $this->actingAs($admin)->post(route('admin.comp-grants.store'), [
            'user_id' => $user->id,
            'prompt_id' => $prompt->id,
            'reason' => $reason,
        ])->assertRedirect();
    }

    $rows = Notification::query()->where('user_id', $user->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->type)->toBe(Notification::TYPE_COMP_GRANT);
});

// ---------------------------------------------------------------------------
// Poll endpoint + UI
// ---------------------------------------------------------------------------

test('the unread poll returns the count and newest 20 in one JSON shape', function () {
    $user = User::factory()->create();

    Notification::emit($user, Notification::TYPE_ORDER_APPROVED, 'Your order #1 was approved.');
    Notification::emit($user, Notification::TYPE_BADGE_AWARDED, 'You earned a badge.');
    $read = Notification::emit($user, Notification::TYPE_FRAME_UNLOCKED, 'A frame was unlocked.');
    $read->forceFill(['read_at' => now()])->save();

    $response = $this->actingAs($user)->getJson(route('notifications.unread'))->assertOk();

    $response->assertJsonStructure(['count', 'items' => [['id', 'type', 'message', 'created_at', 'read_at', 'url']]])
        ->assertJsonPath('count', 2)
        // The list carries the newest 20 regardless of read state (3 rows).
        ->assertJsonCount(3, 'items');

    // Newest first — the read row leads the list but does not count.
    expect($response->json('items.0.message'))->toBe('A frame was unlocked.')
        // Click-through goes through the mark-read route, never straight to
        // the subject URL (found by the F6 browser gate).
        ->and($response->json('items.0.url'))->toBe(route('notifications.open', $read));

    // Hard ceiling: 25 unread → chip 25, list 20.
    for ($i = 0; $i < 25; $i++) {
        Notification::emit($user, Notification::TYPE_VERIFIED_GRANTED, "Filler {$i}");
    }

    $this->actingAs($user)->getJson(route('notifications.unread'))
        ->assertOk()
        ->assertJsonPath('count', 27)
        ->assertJsonCount(20, 'items');
});

test('the unread poll is throttled at 30 requests per minute', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)->getJson(route('notifications.unread'))->assertOk();
    }

    $this->actingAs($user)->getJson(route('notifications.unread'))->assertStatus(429);
});

test('per-item mark-read is owner-only, idempotent, and redirects to the subject', function () {
    [$order] = notificationOrderFixture();
    $notification = Notification::emit($order->buyer, Notification::TYPE_ORDER_APPROVED, 'Your order was approved.', $order);

    $this->actingAs($order->buyer)
        ->get(route('notifications.open', $notification))
        ->assertRedirect(route('checkout.show', $order));

    $firstReadAt = $notification->refresh()->read_at;
    expect($firstReadAt)->not->toBeNull();

    // Re-opening changes nothing (idempotent click-through).
    $this->actingAs($order->buyer)
        ->get(route('notifications.open', $notification))
        ->assertRedirect(route('checkout.show', $order));

    expect($notification->refresh()->read_at->equalTo($firstReadAt))->toBeTrue();

    // A stranger can never open someone else's notification.
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('notifications.open', $notification))->assertForbidden();
});

test('mark all read clears the unread count and is idempotent', function () {
    $user = User::factory()->create();
    Notification::emit($user, Notification::TYPE_ORDER_APPROVED, 'One.');
    Notification::emit($user, Notification::TYPE_ORDER_REJECTED, 'Two.');

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->notifications()->unread()->count())->toBe(0);

    // Second post: zero rows updated, still a clean success (idempotent).
    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->notifications()->unread()->count())->toBe(0);
});

test('the bell is absent for guests and served with its count for signed-in users', function () {
    $guest = $this->get(route('home'))->assertOk()->getContent();

    expect(str_contains($guest, 'data-testid="notification-bell"'))->toBeFalse()
        ->and(str_contains($guest, route('notifications.read-all')))->toBeFalse();

    $user = User::factory()->create();
    Notification::emit($user, Notification::TYPE_VERIFIED_GRANTED, 'Your account is now verified.');

    $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect(str_contains($html, 'data-testid="notification-bell"'))->toBeTrue()
        ->and(str_contains($html, 'data-testid="notification-unread-chip"'))->toBeTrue()
        // Server-rendered first paint carries the item and its mono timestamp.
        ->and(str_contains($html, 'Your account is now verified.'))->toBeTrue()
        ->and(preg_match('/data-testid="notification-unread-chip"[^>]*>1</', $html))->toBe(1)
        // Copy rule: "Notifications", never "real-time" (cPanel has no websockets).
        ->and(preg_match('/real[\s-]?time/i', $html))->toBe(0);

    // The lock also covers the two sources the page depends on.
    foreach (['views/components/notification-bell.blade.php', 'js/app.js'] as $source) {
        expect(preg_match('/real[\s-]?time/i', (string) file_get_contents(resource_path($source))))->toBe(0);
    }
});
