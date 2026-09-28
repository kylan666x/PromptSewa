<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A3: the whole admin surface, swept for the access ladder on every route.
 * Guests bounce to login; members 403; moderators pass the perimeter but
 * 403 on admin-only actions; admins pass everywhere.
 */
test('guests bounce to login from every admin route', function () {
    $routes = adminGetRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $this->get($route)->assertRedirect(route('login'));
    }
});

test('members are forbidden on every admin route', function () {
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    foreach (adminGetRoutes() as $route) {
        $this->actingAs($member)->get($route)->assertForbidden();
    }
});

test('admins pass on every admin GET route', function () {
    seedAdminSurfaceWorld();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    foreach (adminGetRoutes() as $route) {
        $this->actingAs($admin)->get($route)->assertOk();
    }
});

test('moderators are forbidden on admin-only action routes', function () {
    seedAdminSurfaceWorld();
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $target = User::factory()->create(['role' => User::ROLE_CREATOR]);

    // Admin-only PATCH endpoints (role, verify) — moderator tier 403s.
    $this->actingAs($mod)->patch(route('admin.users.role', $target), ['role' => User::ROLE_MEMBER])
        ->assertForbidden();
    $this->actingAs($mod)->patch(route('admin.users.verified', $target))
        ->assertForbidden();
});

/** Every GET-renderable admin surface (binders need seeded rows). */
function adminGetRoutes(): array
{
    seedAdminSurfaceWorld();

    return [
        route('admin.dashboard'),
        route('admin.prompts.index'),
        route('admin.users.index'),
        route('admin.reports.index'),
        route('admin.orders.index'),
        route('admin.packs.index'),
        route('admin.packs.create'),
        route('admin.tool-logos.index'),
        route('admin.brand.edit'),
        route('admin.payments.edit'),
        route('admin.update'),
    ];
}

/** Minimal rows so route-model bindings resolve during the sweep. */
function seedAdminSurfaceWorld(): void
{
    if (\App\Models\ToolLogo::query()->exists()) {
        return; // already seeded within this test's database
    }

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = \App\Models\Prompt::factory()->pending()->for($creator, 'creator')->create();
    \App\Models\PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'Body.',
        'tags' => ['audit'],
        'user_id' => $creator->id,
        'status' => \App\Models\PromptVersion::STATUS_PENDING,
    ]);
    \App\Models\PromptReport::factory()->create();
    \App\Models\Order::factory()->create();
    \App\Models\Pack::factory()->create();
    \App\Models\ToolLogo::query()->create(['name' => 'Audit Tool', 'is_active' => true, 'position' => 1]);
}
