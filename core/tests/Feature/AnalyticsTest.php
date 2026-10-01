<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\PromptDailyStat;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * G6 (v1.7.0) — analytics gates. Views are STATS (upserts allowed), never
 * money. Empty series is a first-class state (the K-series lesson): every
 * series is a dense 30-point array, zero data renders clean.
 */

function viewedPrompt(): array
{
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->create();

    return [$creator, $prompt];
}

test('owner and staff views are excluded from counting', function () {
    [$creator, $prompt] = viewedPrompt();

    $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    // NOTE: actingAs persists across requests within a test (the guard
    // keeps the user), so the GUEST check must run FIRST — after any
    // actingAs call, a bare $this->get() is still authenticated.

    // Guest view: counted.
    $this->get(route('prompts.show', $prompt))->assertOk();
    expect($prompt->refresh()->views_count)->toBe(1);

    // Owner view: not counted.
    $this->actingAs($creator)->get(route('prompts.show', $prompt))->assertOk();
    expect($prompt->refresh()->views_count)->toBe(1);

    // Staff view: not counted.
    $this->actingAs($staff)->get(route('prompts.show', $prompt))->assertOk();
    expect($prompt->refresh()->views_count)->toBe(1);
});

test('views dedupe once per session-hour per prompt', function () {
    [$creator, $prompt] = viewedPrompt();

    $analytics = app(AnalyticsService::class);
    $request = \Illuminate\Http\Request::create('/prompts/'.$prompt->slug, 'GET');
    $request->setLaravelSession(app('session.store'));

    expect($analytics->recordView($request, $prompt))->toBeTrue();

    // Same session, immediately again: deduped.
    expect($analytics->recordView($request, $prompt))->toBeFalse()
        ->and($prompt->refresh()->views_count)->toBe(1);

    // An hour later, same session: counted again.
    $request->session()->put('viewed:'.$prompt->id, now()->subHours(2)->toIso8601String());

    expect($analytics->recordView($request, $prompt))->toBeTrue()
        ->and($prompt->refresh()->views_count)->toBe(2);
});

test('the daily stat upserts one row per prompt per day', function () {
    [$creator, $prompt] = viewedPrompt();

    $analytics = app(AnalyticsService::class);
    $request = \Illuminate\Http\Request::create('/prompts/'.$prompt->slug, 'GET');
    $request->setLaravelSession(app('session.store'));

    $analytics->recordView($request, $prompt);
    $request->session()->put('viewed:'.$prompt->id, now()->subHours(2)->toIso8601String());
    $analytics->recordView($request, $prompt);

    expect(PromptDailyStat::query()->count())->toBe(1)
        ->and(PromptDailyStat::query()->first()->views)->toBe(2)
        ->and(PromptDailyStat::query()->first()->day->toDateString())->toBe(now()->toDateString());
});

test('series builders return dense 30-point arrays on empty data', function () {
    // The cache is the DATABASE store (cPanel parity) — RefreshDatabase
    // resets the data tables but NOT the cache table, so a series built in
    // an earlier test leaks into this one. Clear it for the empty-data
    // assertions.
    cache()->flush();

    $creator = User::factory()->create();
    $analytics = app(AnalyticsService::class);

    // NOTE: usersSeries legitimately counts the $creator just created —
    // it is the only series that can be nonzero in an "empty" world. It is
    // asserted dense + ≥1 rather than zero.

    $zeroable = [
        $analytics->viewsSeries(),
        $analytics->viewsSeries(999),
        $analytics->salesSeries(),
        $analytics->salesSeries($creator, countOnly: true),
        $analytics->ratingSeries($creator),
        $analytics->reportsSeries(),
    ];

    foreach ($zeroable as $series) {
        expect($series)->toBeArray()
            ->and(count($series))->toBe(30, 'series must be dense — 30 points, never sparse')
            ->and(array_sum($series))->toEqual(0); // rating series divides by 100 → float 0.0
    }

    // usersSeries: dense, and counts at least the creator created above.
    $users = $analytics->usersSeries();
    expect($users)->toBeArray()
        ->and(count($users))->toBe(30)
        ->and(array_sum($users))->toBeGreaterThanOrEqual(1);
});

test('sales series uses paid_at set by settleOrder', function () {
    // A paid order from the settle path lands in the series window.
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->create(['price_cents' => 0]);
    $product = Product::factory()->for($prompt, 'prompt')->create(['price_paisa' => 100_00]);
    $order = Order::factory()->create(['buyer_id' => User::factory()->create()->id]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id, 'prompt_id' => $prompt->id, 'price_paisa' => 100_00]);

    app(\App\Services\WalletService::class)->settleOrder($order, 'manual');

    expect($order->refresh()->paid_at)->not->toBeNull();

    cache()->forget('analytics:sales:all:count');

    $series = app(AnalyticsService::class)->salesSeries(countOnly: true);

    expect(array_sum($series))->toBe(1);
});

test('sparkline renders an empty series as a clean baseline', function () {
    $html = view('components.sparkline', ['series' => [], 'label' => 'Empty test'])->render();

    // Valid SVG with a baseline polyline — never a broken chart.
    expect($html)->toContain('<svg')
        ->and($html)->toContain('<polyline')
        ->and($html)->not->toContain('NaN')
        ->and($html)->not->toContain('null');
});

test('admin overview renders with zero orders', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    expect($response->getContent())->toContain('Revenue · 30d')
        ->and($response->getContent())->toContain('Rs. 0.00');

    assertNoBladeLeak($response);
});

test('creator stats tab renders clean with zero data', function () {
    $creator = User::factory()->create();

    $response = $this->actingAs($creator)->get(route('dashboard', ['tab' => 'stats']));

    $response->assertOk();
    expect($response->getContent())->toContain('Views — last 30 days')
        ->and($response->getContent())->toContain('No published prompts yet');

    assertNoBladeLeak($response);
});
