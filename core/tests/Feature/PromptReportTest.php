<?php

use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function staffUser(string $role = User::ROLE_ADMIN): User
{
    return User::factory()->create(['role' => $role]);
}

// --- Public reporting ------------------------------------------------------

test('guests can open the report form for a public prompt', function () {
    $prompt = Prompt::factory()->published()->create();

    $this->get(route('prompts.report.create', $prompt))
        ->assertOk()
        ->assertSee('report');
});

test('hidden prompts return 404 for the report form', function () {
    $draft = Prompt::factory()->draft()->create();

    $this->get(route('prompts.report.create', $draft))->assertNotFound();

    $private = Prompt::factory()->published()->private()->create();
    $this->get(route('prompts.report.create', $private))->assertNotFound();
});

test('guests can submit a report with a contact email', function () {
    $prompt = Prompt::factory()->published()->create();

    $this->post(route('prompts.report.store', $prompt), [
        'reason' => 'stolen',
        'message' => 'This prompt was copied verbatim from my paid pack.',
        'reporter_email' => 'victim@example.test',
    ])
        ->assertRedirect(route('prompts.show', $prompt))
        ->assertSessionHas('report_submitted');

    $this->assertDatabaseHas('prompt_reports', [
        'prompt_id' => $prompt->id,
        'user_id' => null,
        'reason' => 'stolen',
        'reporter_email' => 'victim@example.test',
        'status' => PromptReport::STATUS_OPEN,
    ]);
});

test('logged-in members are linked to their report automatically', function () {
    $member = User::factory()->create();
    $prompt = Prompt::factory()->published()->create();

    $this->actingAs($member)
        ->post(route('prompts.report.store', $prompt), [
            'reason' => 'spam',
            'message' => 'This listing is pure advertising with no prompt inside.',
        ])
        ->assertRedirect(route('prompts.show', $prompt));

    $this->assertDatabaseHas('prompt_reports', [
        'prompt_id' => $prompt->id,
        'user_id' => $member->id,
        'reason' => 'spam',
    ]);
});

test('report submission validates reason and minimum message length', function () {
    $prompt = Prompt::factory()->published()->create();

    $this->post(route('prompts.report.store', $prompt), [
        'reason' => 'not-a-real-reason',
        'message' => 'short',
    ])->assertSessionHasErrors(['reason', 'message']);

    $this->assertDatabaseCount('prompt_reports', 0);
});

test('reporting respects prompt ownership for private listings', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $private = Prompt::factory()->published()->private()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->post(route('prompts.report.store', $private), [
            'reason' => 'other',
            'message' => 'This should never be storable for a hidden prompt.',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('prompt_reports', 0alam);
});
