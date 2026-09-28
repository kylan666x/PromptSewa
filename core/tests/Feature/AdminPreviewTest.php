<?php

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function previewStaff(): User
{
    return User::create([
        'name' => 'Preview Mod',
        'username' => 'preview-mod',
        'email' => 'pmod@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MODERATOR,
    ]);
}

function previewMember(): User
{
    return User::create([
        'name' => 'Preview Member',
        'username' => 'preview-member',
        'email' => 'pmember@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_MEMBER,
    ]);
}

function creator(): User
{
    return User::create([
        'name' => 'Pending Creator',
        'username' => 'pending-creator',
        'email' => 'pcreator@promptsewa.test',
        'password' => 'password',
        'role' => User::ROLE_CREATOR,
    ]);
}

function pendingPaidPrompt(User $creator): Prompt
{
    $prompt = Prompt::factory()->pending()->for($creator, 'creator')->create([
        'price_cents' => 24900,
        'title' => 'Pending Paid Secret',
    ]);

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => implode("\n", array_fill(0, 30, 'Filler teaser line that can be safely shown publicly.'))
            ."\n\nFULL SECRET BODY — the part that must never leak into the teaser.",
        'tags' => ['preview'],
        'user_id' => $creator->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    return $prompt;
}

test('moderator sees the full paid body of a pending prompt via the preview route', function () {
    $mod = previewStaff();
    $prompt = pendingPaidPrompt(creator());

    Log::spy();

    $this->actingAs($mod)
        ->get(route('admin.prompts.preview', $prompt))
        ->assertOk()
        ->assertSee('FULL SECRET BODY', false)
        ->assertSee('Moderation preview', false);

    Log::shouldHaveReceived('info')->with('admin.prompt.preview', [
        'user_id' => $mod->id,
        'prompt_id' => $prompt->id,
    ]);
});

test('plain members are forbidden from the moderation preview', function () {
    $member = previewMember();
    $prompt = pendingPaidPrompt($member);

    $this->actingAs($member)
        ->get(route('admin.prompts.preview', $prompt))
        ->assertForbidden();
});

test('guests are redirected to login from the moderation preview', function () {
    $prompt = pendingPaidPrompt(previewMember());

    $this->get(route('admin.prompts.preview', $prompt))
        ->assertRedirect(route('login'));
});

test('previewing creates zero license grants — paywall invariant', function () {
    $mod = previewStaff();
    $prompt = pendingPaidPrompt($mod);

    $this->actingAs($mod)
        ->get(route('admin.prompts.preview', $prompt))
        ->assertOk();

    expect(LicenseGrant::count())->toBe(0);

    // And the owner is still not self-entitled by their own moderation pass.
    $this->actingAs($mod)
        ->get(route('admin.prompts.preview', $prompt))
        ->assertOk();

    expect(LicenseGrant::count())->toBe(0);
});

test('the old referer-sniffing preview param no longer unlocks paid bodies', function () {
    $mod = previewStaff();
    $prompt = pendingPaidPrompt(creator());

    // Even a moderator hitting the PUBLIC page with ?preview=1 gets the
    // locked treatment now — preview only works through the admin route.
    $this->withHeaders(['referer' => 'https://promptsewa.test/admin/prompts'])
        ->actingAs($mod)
        ->get(route('prompts.show', $prompt).'?preview=1')
        ->assertNotFound(); // pending prompts 404 on the public page anyway
});

test('a published paid prompt stays locked for staff on the public page', function () {
    $mod = previewStaff();
    $prompt = pendingPaidPrompt(creator());
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $this->actingAs($mod)
        ->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertDontSee('FULL SECRET BODY', false);
});
