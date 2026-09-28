<?php

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function versionsCreator(): User
{
    return User::factory()->create(['role' => User::ROLE_CREATOR, 'username' => 'verocreator']);
}

function versionedPrompt(User $creator, array $overrides = []): Prompt
{
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create(array_merge([
        'title' => 'History Probe',
        'price_cents' => 0,
    ], $overrides));

    $bodies = ['Free body v1.', 'Free body v2 with more detail.', 'Free body v3 final.'];
    foreach ([1, 2, 3] as $i) {
        PromptVersion::create([
            'prompt_id' => $prompt->id,
            'version_number' => $i,
            'body' => $bodies[$i - 1],
            'changelog' => "changelog note {$i}",
            'tags' => ['history'],
            'user_id' => $creator->id,
            'status' => PromptVersion::STATUS_PUBLISHED,
        ]);
    }

    return $prompt;
}

test('guests see the versions link and the history page', function () {
    $creator = versionsCreator();
    $prompt = versionedPrompt($creator);

    $this->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee(route('prompts.versions', $prompt), false)
        ->assertSee('View history');

    $this->get(route('prompts.versions', $prompt))
        ->assertOk()
        ->assertSee('changelog note 1')
        ->assertSee('changelog note 2')
        ->assertSee('changelog note 3')
        ->assertSee('v1')->assertSee('v2')->assertSee('v3')
        ->assertSee($creator->name);
});

test('unrelated member sees versions but zero edit links', function () {
    $prompt = versionedPrompt(versionsCreator());
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $detail = $this->actingAs($member)->get(route('prompts.show', $prompt))->getContent();

    expect(str_contains($detail, 'View history'))->toBeTrue()
        ->and(str_contains($detail, route('dashboard.prompts.edit', $prompt)))->toBeFalse('non-owner saw an edit URL');

    $this->actingAs($member)->get(route('prompts.versions', $prompt))->assertOk();
});

test('owner sees the edit link; moderators do not', function () {
    $creator = versionsCreator();
    $prompt = versionedPrompt($creator);
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);

    expect(str_contains(
        $this->actingAs($creator)->get(route('prompts.show', $prompt))->getContent(),
        route('dashboard.prompts.edit', $prompt),
    ))->toBeTrue('owner lost their edit link');

    $modView = $this->actingAs($mod)->get(route('prompts.show', $prompt))->getContent();
    expect(str_contains($modView, route('dashboard.prompts.edit', $prompt)))->toBeFalse('moderator saw edit link')
        ->and(str_contains($modView, route('prompts.versions', $prompt)))->toBeTrue();

    // Moderator path is the admin preview route, unaffected.
    $this->actingAs($mod)->get(route('admin.prompts.preview', $prompt))->assertOk();
});

test('paid prompt history is metadata only — no body fragments leak', function () {
    $creator = versionsCreator();
    $prompt = versionedPrompt($creator, ['price_cents' => 24_900]);

    $secretFragment = 'Free body v2 with more detail';
    $page = $this->get(route('prompts.versions', $prompt))->getContent();

    expect(str_contains($page, $secretFragment))->toBeFalse('body fragment leaked to the history page')
        ->and(str_contains($page, 'changelog note 2'))->toBeTrue();
});

test('admin review queue links to the preview route, not the owner edit path', function () {
    $prompt = versionedPrompt(versionsCreator());
    $prompt->update(['status' => Prompt::STATUS_PENDING]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();
    $editUrl = route('dashboard.prompts.edit', $prompt);

    // The review queue must not send staff through the owner-only edit route.
    expect($html)->not->toContain($editUrl.'"', 'review queue links the owner edit path');
});
