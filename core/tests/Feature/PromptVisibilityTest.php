<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeVersionFor(Prompt $prompt): void
{
    PromptVersion::create([
        'prompt_id' => $prompt->id,
        'version_number' => 1,
        'body' => 'Secret internal body text.',
        'tags' => ['internal'],
        'user_id' => $prompt->user_id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);
}

test('draft prompts never appear on the storefront or library', function () {
    $draft = Prompt::factory()->draft()->create(['title' => 'Secret Draft Probe']);
    makeVersionFor($draft);

    $this->get('/')->assertOk()->assertDontSee('Secret Draft Probe');
    $this->get('/prompts')->assertOk()->assertDontSee('Secret Draft Probe');
});

test('private published prompts never appear on the storefront or library', function () {
    $private = Prompt::factory()->published()->private()->create(['title' => 'Private Vault Probe']);
    makeVersionFor($private);

    $this->get('/')->assertOk()->assertDontSee('Private Vault Probe');
    $this->get('/prompts')->assertOk()->assertDontSee('Private Vault Probe');
});

test('pending and rejected prompts never appear publicly', function () {
    $pending = Prompt::factory()->pending()->create(['title' => 'Pending Review Probe']);
    $rejected = Prompt::factory()->create(['title' => 'Rejected Probe', 'status' => Prompt::STATUS_REJECTED]);
    makeVersionFor($pending);
    makeVersionFor($rejected);

    $this->get('/prompts')
        ->assertOk()
        ->assertDontSee('Pending Review Probe')
        ->assertDontSee('Rejected Probe');
});

test('hidden prompts are excluded from scout search results', function () {
    Prompt::factory()->draft()->create(['title' => 'Hidden Draft Needle']);
    Prompt::factory()->published()->private()->create(['title' => 'Hidden Private Needle']);

    $this->get('/prompts?q=needle')
        ->assertOk()
        ->assertDontSee('Hidden Draft Needle')
        ->assertDontSee('Hidden Private Needle');
});

test('guests receive 404 for private and draft prompt detail pages', function () {
    $private = Prompt::factory()->published()->private()->create();
    $draft = Prompt::factory()->draft()->create();

    $this->get('/prompts/'.$private->slug)->assertNotFound();
    $this->get('/prompts/'.$draft->slug)->assertNotFound();
});

test('owners can view their own private prompt detail page', function () {
    $owner = User::factory()->create();
    $private = Prompt::factory()->published()->private()->create(['user_id' => $owner->id]);
    makeVersionFor($private);

    $this->actingAs($owner)
        ->get('/prompts/'.$private->slug)
        ->assertOk()
        ->assertSee($private->title);
});

test('other members cannot view a private prompt', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $private = Prompt::factory()->published()->private()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->get('/prompts/'.$private->slug)
        ->assertNotFound();
});

test('category pages exclude non-public prompts', function () {
    $draft = Prompt::factory()->draft()->create(['title' => 'Category Draft Probe']);
    $public = Prompt::factory()->published()->create(['title' => 'Category Public Probe']);
    makeVersionFor($draft);
    makeVersionFor($public);

    $this->get('/categories/'.$public->category->slug)
        ->assertOk()
        ->assertSee('Category Public Probe')
        ->assertDontSee('Category Draft Probe');
});
