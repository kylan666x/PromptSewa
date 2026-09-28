<?php

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function moderator(): User
{
    return User::create([
        'name' => 'Mod Person',
        'username' => 'mod-person',
        'email' => 'mod@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MODERATOR,
    ]);
}

function member(): User
{
    return User::create([
        'name' => 'Plain Member',
        'username' => 'plain-member',
        'email' => 'member@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);
}

function pendingPrompt(User $creator, array $overrides = []): Prompt
{
    $prompt = Prompt::factory()->pending()->for($creator, 'creator')->create($overrides);

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'Secret pending body — only staff preview may see this.',
        'tags' => ['review'],
        'user_id' => $creator->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    return $prompt;
}

test('approve via the PATCH-spoofed admin form transitions pending to published', function () {
    $mod = moderator();
    $prompt = pendingPrompt($mod, ['price_cents' => 24900]);

    $this->actingAs($mod)
        ->from(route('admin.prompts.index'))
        ->post(route('admin.prompts.status', $prompt), [
            '_method' => 'PATCH',
            'status' => Prompt::STATUS_PUBLISHED,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_PUBLISHED);
});

test('reject stores the rejected status without a reason column requirement', function () {
    $mod = moderator();
    $prompt = pendingPrompt($mod);

    $this->actingAs($mod)
        ->post(route('admin.prompts.status', $prompt), [
            '_method' => 'PATCH',
            'status' => Prompt::STATUS_REJECTED,
        ])
        ->assertRedirect();

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_REJECTED);
});

test('review actions are moderator-only — plain members get 403', function () {
    $member = member();
    $prompt = pendingPrompt($member);

    $this->actingAs($member)
        ->post(route('admin.prompts.status', $prompt), [
            '_method' => 'PATCH',
            'status' => Prompt::STATUS_PUBLISHED,
        ])
        ->assertForbidden();

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_PENDING);
});

test('guests are redirected away from the review queue', function () {
    $prompt = pendingPrompt(member());

    $this->post(route('admin.prompts.status', $prompt), [
        '_method' => 'PATCH',
        'status' => Prompt::STATUS_PUBLISHED,
    ])->assertRedirect(route('login'));

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_PENDING);
});

test('posting without the method spoof is rejected (405 regression lock)', function () {
    $mod = moderator();
    $prompt = pendingPrompt($mod);

    // A plain POST (no _method=PATCH) must NOT be able to flip status.
    $this->actingAs($mod)
        ->post(route('admin.prompts.status', $prompt), [
            'status' => Prompt::STATUS_PUBLISHED,
        ])
        ->assertStatus(405);

    expect($prompt->refresh()->status)->toBe(Prompt::STATUS_PENDING);
});

test('approval does not create entitlements — paywall invariant intact', function () {
    $mod = moderator();
    $prompt = pendingPrompt($mod, ['price_cents' => 24900]);

    $this->actingAs($mod)
        ->post(route('admin.prompts.status', $prompt), [
            '_method' => 'PATCH',
            'status' => Prompt::STATUS_PUBLISHED,
        ])
        ->assertRedirect();

    $prompt->refresh();

    expect(LicenseGrant::count())->toBe(0)
        ->and($prompt->status)->toBe(Prompt::STATUS_PUBLISHED);
});
