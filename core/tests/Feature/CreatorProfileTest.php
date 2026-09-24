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
    $creator = creatorWithPrompt(['name' => 'JustShipItAI'], ['title' => 'Signature Prompt']);

    $this->get(route('creators.show', $creator))
        ->assertOk()
        ->assertSee('JustShipItAI')
        ->assertSee('Ships AI products.')
        ->assertSee('Signature Prompt')
        ->assertSee(route('prompts.show', $prompt ?? Prompt::first()), false);
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

test('prompt cards link to the creator profile', function () {
    $creator = creatorWithPrompt(['name' => 'JustShipItAI']);

    $this->get('/prompts')
        ->assertOk()
        ->assertSee(route('creators.show', $creator), false)
        ->assertSee('View profile of JustShipItAI', false);
});

test('prompt detail page links to the creator profile', function () {
    $creator = creatorWithPrompt(['name' => 'JustShipItAI']);

    $prompt = $creator->prompts()->first();

    $this->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee(route('creators.show', $creator), false);
});
