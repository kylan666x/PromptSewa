<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B1: views must render standalone — no ambient shared variables. The prod
 * 500 on /admin/update was "Undefined variable $errors" inside a compiled
 * view: any view that only renders while ShareErrorsFromSession is on the
 * stack breaks the moment it is rendered from another context (artisan,
 * cached compile drift, error paths). A view that needs ambient globals is
 * a release blocker.
 */

function staffSeededWorld(): User
{
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $category = Category::factory()->create();
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create(['category_id' => $category->id]);
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create();
    Pack::factory()->create();
    PromptReport::factory()->create();
    // Global beforeEach already seeds the registry — firstOrCreate here.
    ToolLogo::query()->firstOrCreate(['name' => 'Midjourney'], ['is_active' => true, 'position' => 1]);

    return $admin;
}

// ---------------------------------------------------------------------------
// TRUE standalone render: no HTTP round-trip, no web middleware, no $errors
// ---------------------------------------------------------------------------

test('dashboard update view renders standalone without ambient globals', function () {
    $html = view('dashboard.update', ['zipLimit' => 104857600])->render();

    expect($html)->not->toContain('{{')
        ->and($html)->toContain('Update PromptSewa');
});

test('dashboard update view renders standalone with a log and failure state', function () {
    $html = view('dashboard.update', [
        'zipLimit' => 104857600,
        'log' => collect([['level' => 'ok', 'line' => 'migrate complete'], ['level' => 'bad', 'line' => 'boom']]),
        'failed' => true,
        'tokenHint' => 'missing',
    ])->render();

    expect($html)->not->toContain('{{')
        ->and($html)->toContain('Update failed')
        ->and($html)->toContain('Update token missing');
});

// ---------------------------------------------------------------------------
// Every staff surface renders through the full stack without leaking
// ---------------------------------------------------------------------------

test('every admin page renders clean for staff', function () {
    $admin = staffSeededWorld();

    $routes = [
        'admin.dashboard',
        'admin.prompts.index',
        'admin.users.index',
        'admin.reports.index',
        'admin.orders.index',
        'admin.packs.index',
        'admin.packs.create',
        'admin.tool-logos.index',
        'admin.brand.edit',
        'admin.payments.edit',
        'admin.update',
    ];

    foreach ($routes as $route) {
        $response = $this->actingAs($admin)->get(route($route));
        expect($response->status())->toBe(200, "route {$route} did not render");
        assertNoBladeLeak($response);
    }
});

test('key dashboard pages render clean for their owners', function () {
    $admin = staffSeededWorld();

    $pages = [
        ['route' => 'dashboard', 'expect' => 200],
        ['route' => 'dashboard.profile.edit', 'expect' => 200],
        ['route' => 'dashboard.prompts.create', 'expect' => 200],
    ];

    foreach ($pages as ['route' => $route, 'expect' => $status]) {
        $response = $this->actingAs($admin)->get(route($route));
        expect($response->status())->toBe($status, "route {$route} did not render");
        assertNoBladeLeak($response);
    }

    // Edit needs an owned prompt.
    $prompt = Prompt::factory()->published()->for($admin, 'creator')->create();
    $response = $this->actingAs($admin)->get(route('dashboard.prompts.edit', $prompt));
    expect($response->status())->toBe(200);
    assertNoBladeLeak($response);
});
