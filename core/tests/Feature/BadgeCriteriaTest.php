<?php

use App\Models\Badge;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\CriterionEvaluator;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * F3 (v1.7.8) — badge criteria admin + backfill (BH-R9-03).
 *
 * The reproduction found the evaluator healthy end-to-end (a matching badge
 * + a real event → award, XP and feed row). The actual holes were:
 *  - `verified` had no emitter: the flag flip never evaluated criteria;
 *  - `manual` was not offered as a badge criterion;
 *  - no one-line descriptions, so staff-judged/manual looked broken;
 *  - historical eligibility had no backfill path.
 * These locks pin each fix from the admin's own served surfaces.
 */

test('the served badge form offers every evaluator criterion with a one-line description', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->getContent();

    // DESCRIPTIONS covers every criterion exactly (single source, no drift).
    expect(array_keys(CriterionEvaluator::DESCRIPTIONS))->toBe(CriterionEvaluator::CRITERIA);

    foreach (CriterionEvaluator::CRITERIA as $criterion) {
        expect(str_contains($html, 'value="'.$criterion.'"'))->toBeTrue();
    }

    foreach (CriterionEvaluator::DESCRIPTIONS as $description) {
        expect(str_contains($html, $description))->toBeTrue();
    }

    // Manual-only is explicitly selectable — the F3 gap.
    expect(str_contains($html, 'value="manual"'))->toBeTrue();
});

test('a criterion set through the served form auto-awards when the sale event fires', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create();

    // Set the criterion through the SERVED form (payload never hand-built).
    $html = $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->getContent();
    $form = raidFormExtract($html, route('admin.badges.store'));

    raidFormSubmit($this, $admin, $form, [
        'name' => 'First Sale',
        'slug' => 'first-sale',
        'criterion' => 'first_sale',
        'is_active' => '1',
    ])->assertRedirect();

    $badge = Badge::query()->where('slug', 'first-sale')->sole();

    expect($badge->criterion)->toBe('first_sale')
        ->and($badge->is_active)->toBeTrue()
        ->and(UserBadge::query()->count())->toBe(0);

    // The real sale event: settle an order through the same service the
    // admin approval path uses (OrderObserver fires on the paid transition).
    $buyer = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->create(['price_cents' => 0]);
    $product = Product::factory()->for($prompt, 'prompt')->create(['price_paisa' => 100_00]);

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

    app(WalletService::class)->settleOrder($order, 'manual');

    expect(UserBadge::query()->where('user_id', $creator->id)->where('badge_id', $badge->id)->count())->toBe(1);
});

test('pv:award-scan backfills historical eligibility exactly once', function () {
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->priced(19900)->published()->create();

    // F1 (v1.9.2): 12 REAL sales — paid order lines, the only sales truth.
    foreach (range(1, 12) as $_) {
        recordPaidSale($prompt, User::factory()->create(), 19_900);
    }

    // Badge created AFTER the sales were recorded — no observer ever fired.
    Badge::query()->create([
        'name' => 'Ten Sales', 'slug' => 'ten-sales',
        'criterion' => 'sales_10', 'is_active' => true,
    ]);

    expect(UserBadge::query()->count())->toBe(0);

    $this->artisan('pv:award-scan')
        ->expectsOutputToContain('awarded 1 badge')
        ->assertSuccessful();

    expect(UserBadge::query()->where('user_id', $creator->id)->count())->toBe(1);

    // Second run: the UNIQUE(user_id, badge_id) gate dedupes — a no-op.
    $this->artisan('pv:award-scan')
        ->expectsOutputToContain('awarded 0 badge')
        ->assertSuccessful();

    expect(UserBadge::query()->count())->toBe(1);
});

test('granting verified through the served admin form awards verified badges', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();

    Badge::query()->create([
        'name' => 'Verified', 'slug' => 'verified',
        'criterion' => 'verified', 'is_active' => true,
    ]);

    $this->actingAs($admin)->patch(route('admin.users.verified', $member))->assertRedirect();

    expect($member->refresh()->is_verified)->toBeTrue()
        ->and(UserBadge::query()->where('user_id', $member->id)->count())->toBe(1);

    // Revoking keeps the earned row — badges are history, never clawed back.
    $this->actingAs($admin)->patch(route('admin.users.verified', $member))->assertRedirect();

    expect($member->refresh()->is_verified)->toBeFalse()
        ->and(UserBadge::query()->where('user_id', $member->id)->count())->toBe(1);
});

test('the Scan now button is served, admin-only, throttled, and scheduled daily', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    $html = $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->getContent();
    expect(str_contains($html, route('admin.badges.scan')))->toBeTrue();

    // Moderators pass the staff perimeter but are refused by the controller.
    $this->actingAs($moderator)->post(route('admin.badges.scan'))->assertForbidden();

    $this->actingAs($admin)->post(route('admin.badges.scan'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($candidate) => $candidate->getName() === 'admin.badges.scan');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:6,1');

    // The daily cron row exists in the schedule (source-locked like the
    // other deployment contracts).
    expect(str_contains((string) file_get_contents(base_path('routes/console.php')), "'pv:award-scan'"))->toBeTrue();
});
