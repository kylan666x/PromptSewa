<?php

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\User;
use App\Services\CompGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function compAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

test('admin issues a comp grant that unlocks the paid prompt', function () {
    $admin = compAdmin();
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create(['price_cents' => 24_900]);

    $this->actingAs($admin)
        ->post(route('admin.comp-grants.store'), [
            'user_id' => $user->id,
            'prompt_id' => $prompt->id,
            'reason' => 'Press copy for TechSamachar review',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $grant = LicenseGrant::query()->where('user_id', $user->id)->sole();
    expect($grant->license_tier)->toBe('comp')
        ->and($grant->issued_by)->toBe($admin->id)
        ->and($grant->issue_reason)->toBe('Press copy for TechSamachar review')
        ->and($grant->order_item_id)->toBeNull()
        ->and($grant->status)->toBe(LicenseGrant::STATUS_ACTIVE);

    // Paywall equivalence is locked separately in comp access test below.
});

test('comp grants are idempotent while active', function () {
    $admin = compAdmin();
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create();
    $service = app(CompGrantService::class);

    $first = $service->grant($user, $prompt, $admin, 'first issue');
    $second = $service->grant($user, $prompt, $admin, 'accidental double-run');

    expect($first)->toBeInstanceOf(LicenseGrant::class)
        ->and($second)->toBeNull()
        ->and(LicenseGrant::where('user_id', $user->id)->count())->toBe(1);
});

test('comp access matches purchased access on the paywall', function () {
    $admin = compAdmin();
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create([
        'price_cents' => 24_900,
    ]);

    \App\Models\PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => "SECRET-COMP-BODY\nline two",
        'tags' => ['comp'],
        'user_id' => $prompt->user_id,
        'status' => \App\Models\PromptVersion::STATUS_PUBLISHED,
    ]);

    app(CompGrantService::class)->grant($user, $prompt, $admin, 'make-good for failed checkout');

    $this->actingAs($user)
        ->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee('SECRET-COMP-BODY');
});

test('moderators cannot issue comp grants', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create();

    $this->actingAs($mod)
        ->post(route('admin.comp-grants.store'), [
            'user_id' => $user->id,
            'prompt_id' => $prompt->id,
            'reason' => 'mod overreach attempt',
        ])
        ->assertForbidden();

    expect(LicenseGrant::count())->toBe(0);
});

test('revoking a comp keeps the ledger row', function () {
    $admin = compAdmin();
    $user = User::factory()->create();
    $prompt = Prompt::factory()->published()->create();
    $service = app(CompGrantService::class);

    $grant = $service->grant($user, $prompt, $admin, 'temporary access');
    $service->revoke($grant, $admin);

    expect(LicenseGrant::count())->toBe(1)
        ->and($grant->refresh()->status)->toBe(LicenseGrant::STATUS_REVOKED);
});
