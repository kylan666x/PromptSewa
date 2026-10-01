<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * F3 (v1.5.2) — the "Apply adoption" panel on Admin → Users.
 *
 * The founder runs T4 (pv:adopt-catalog) from the browser on the no-SSH
 * cPanel host. The panel SHELLS the command — assertions about what the
 * command does live in CatalogAndVersionTruthTest; here we lock the HTTP
 * surface: admin-only, dry-run writes nothing, force moves the prompts.
 */

function adoptionFixture(): array
{
    $official = User::factory()->create(['username' => 'promptsewa', 'is_official' => true, 'role' => User::ROLE_ADMIN]);
    $demo = User::factory()->create(['email' => 'maya@promptsewa.test']);

    $demoPrompt = Prompt::factory()->for($demo, 'creator')->create();

    return [$official, $demo, $demoPrompt];
}

test('adoption panel preview is admin-only and writes nothing', function () {
    [$official, $demo, $demoPrompt] = adoptionFixture();

    $member = User::factory()->create();
    $this->actingAs($member)
        ->post(route('admin.users.adopt.preview'))
        ->assertForbidden();

    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $this->actingAs($moderator)
        ->post(route('admin.users.adopt.preview'))
        ->assertForbidden();

    // Nothing adopted yet.
    expect($demoPrompt->refresh()->user_id)->toBe($demo->id);

    $this->actingAs($official)
        ->post(route('admin.users.adopt.preview'))
        ->assertRedirect()
        ->assertSessionHas('adoption_preview');

    // Dry run: still nothing adopted.
    expect($demoPrompt->refresh()->user_id)->toBe($demo->id);
});

test('adoption panel run is admin-only and applies the adoption', function () {
    [$official, $demo, $demoPrompt] = adoptionFixture();

    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $this->actingAs($moderator)
        ->post(route('admin.users.adopt.run'))
        ->assertForbidden();

    expect($demoPrompt->refresh()->user_id)->toBe($demo->id);

    $this->actingAs($official)
        ->post(route('admin.users.adopt.run'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($demoPrompt->refresh()->user_id)->toBe($official->id);

    // Idempotent: a second run is a clean no-op (still exit 0 / success).
    $this->actingAs($official)
        ->post(route('admin.users.adopt.run'))
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('admin users page renders the adoption panel with both forms', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.users.index'))->getContent();

    expect($html)->toContain('Apply catalog adoption')
        ->and($html)->toContain(route('admin.users.adopt.preview'))
        ->and($html)->toContain(route('admin.users.adopt.run'));
});
