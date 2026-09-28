<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function creatorWithPrompt(array $userOverrides = [], array $promptOverrides = []): User
{
    $creator = User::create(array_merge([
        'name' => 'JustShipItAI',
        'username' => 'justshipitai',
        'email' => 'justshipit@example.test',
        'password' => 'password',
        'role' => User::ROLE_CREATOR,
        'bio' => 'Ships AI products.',
    ], $userOverrides));

    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create($promptOverrides);

    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'Demo body.',
        'tags' => ['demo'],
        'user_id' => $creator->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    return $creator;
}

test('guests can view a public creator profile with their published prompts', function () {
    $creator = creatorWithPrompt(['name' => 'JustShipItAI', 'username' => 'justshipitai'], ['title' => 'Signature Prompt']);

    $prompt = $creator->prompts()->first();

    $this->get(route('creators.show', $creator))
        ->assertOk()
        ->assertSee('JustShipItAI')
        // B2/B4: the handle must render as text inside the mono component,
        // never as braces (the v1.4.1 @{{ leak class).
        ->assertSee('@justshipitai')
        ->assertSee('font-mono', false)
        ->assertSee('Ships AI products.')
        ->assertSee('Signature Prompt')
        ->assertSee(route('prompts.show', $prompt), false);
});

test('creator profile does not leak drafts or private prompts', function () {
    $creator = creatorWithPrompt();

    Prompt::factory()->draft()->for($creator, 'creator')->create(['title' => 'Secret Draft Probe']);

    $this->get(route('creators.show', $creator))
        ->assertOk()
        ->assertDontSee('Secret Draft Probe');
});

test('soft-deleted creators return 404', function () {
    $creator = creatorWithPrompt();
    $creator->delete();

    $this->get(route('creators.show', $creator->id))->assertNotFound();
});

test('prompt cards link to the creator profile and render the handle, never braces', function () {
    $creator = creatorWithPrompt(['name' => 'JustShipItAI', 'username' => 'justshipitai']);

    $response = $this->get('/prompts')
        ->assertOk()
        ->assertSee(route('creators.show', $creator), false)
        ->assertSee('View profile of JustShipItAI', false)
        ->assertSee('@justshipitai', false);

    assertNoBladeLeak($response);
});

test('prompt detail page links to the creator profile and renders the handle, never braces', function () {
    $creator = creatorWithPrompt(['name' => 'JustShipItAI', 'username' => 'justshipitai']);

    $prompt = $creator->prompts()->first();

    $response = $this->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee(route('creators.show', $creator), false)
        ->assertSee('@justshipitai', false);

    assertNoBladeLeak($response);
});
