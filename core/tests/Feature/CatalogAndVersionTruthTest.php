<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\ToolLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * T6: type-agnostic tool registry for these tests. The global beforeEach
 * seeder runs first with typed defaults, so these force-upsert to 'any'
 * — factory prompts randomize their type.
 */
function seedToolRegistry(): void
{
    foreach (['ChatGPT', 'Claude', 'Midjourney'] as $name) {
        ToolLogo::query()->updateOrCreate(
            ['name' => $name],
            ['modality' => 'any', 'is_active' => true],
        );
    }
}

// ---------------------------------------------------------------- T4

test('pv:adopt-catalog dry-run writes nothing and force moves prompts', function () {
    $official = User::factory()->create(['username' => 'promptsewa', 'is_official' => true, 'role' => User::ROLE_ADMIN]);
    $demo = User::factory()->create(['email' => 'bibek@promptsewa.test']);
    $innocent = User::factory()->create(['email' => 'real.person@example.dev']);

    $demoPrompt = Prompt::factory()->for($demo, 'creator')->create();
    $ownPrompt = Prompt::factory()->for($innocent, 'creator')->create();

    // Dry run: no writes.
    $this->artisan('pv:adopt-catalog', ['--dry-run' => true])->assertExitCode(0);
    expect($demoPrompt->refresh()->user_id)->toBe($demo->id);

    $this->artisan('pv:adopt-catalog', ['--force' => true])->assertExitCode(0);

    expect($demoPrompt->refresh()->user_id)->toBe($official->id)
        ->and($ownPrompt->refresh()->user_id)->toBe($innocent->id);

    // Idempotent: second force is a clean no-op.
    $this->artisan('pv:adopt-catalog', ['--force' => true])->assertExitCode(0);
});

// ---------------------------------------------------------------- T6

test('tool registry validates modality against prompt type', function () {
    ToolLogo::query()->firstOrCreate(['name' => 'Midjourney'], ['modality' => 'image', 'is_active' => true]);
    ToolLogo::query()->firstOrCreate(['name' => 'Claude'], ['modality' => 'text', 'is_active' => true]);
    ToolLogo::query()->firstOrCreate(['name' => 'Copilot'], ['modality' => 'any', 'is_active' => true]);

    $user = User::factory()->create();
    $category = \App\Models\Category::factory()->create();

    $payload = [
        'title' => 'Valid listing title here',
        'description' => str_repeat('A perfectly adequate description. ', 3),
        'category_id' => $category->id,
        'type' => 'video',
        'body' => str_repeat('Prompt body line with enough content. ', 3),
        'tags' => 'video, test',
        'recommended_tools' => ['Midjourney'], // image-only tool on a video prompt
        'price_npr' => 0,
        'visibility' => 'public',
    ];

    $this->actingAs($user)
        ->post(route('dashboard.prompts.store'), $payload)
        ->assertSessionHasErrors('recommended_tools.0');

    // 'any' modality fits everywhere.
    $payload['recommended_tools'] = ['Copilot'];
    $this->actingAs($user)
        ->post(route('dashboard.prompts.store'), $payload)
        ->assertSessionHasNoErrors();

    // The founder error: an unregistered tool name now fails server-side.
    $payload['recommended_tools'] = ['Made Up Tool 42'];
    $this->actingAs($user)
        ->post(route('dashboard.prompts.store'), $payload)
        ->assertSessionHasErrors();
});

test('taxonomy seeder is idempotent and production-shaped', function () {
    $this->artisan('db:seed', ['--class' => 'TaxonomySeeder'])->assertExitCode(0);
    $this->artisan('db:seed', ['--class' => 'TaxonomySeeder'])->assertExitCode(0);

    expect(\App\Models\Category::query()->where('type_scope', 'agentic')->count())->toBe(2)
        ->and(\App\Models\Category::query()->where('type_scope', 'skill')->count())->toBe(2)
        // ToolLogoSeeder baseline contributes 4 video tools; TaxonomySeeder
        // adds Seedance 2.5, Runway, Pika, Veo → 8.
        ->and(ToolLogo::query()->where('modality', 'video')->count())->toBe(8)
        ->and(ToolLogo::query()->where('name', 'CrewAI')->where('modality', 'agentic')->exists())->toBeTrue();
});

// ---------------------------------------------------------------- T7

test('editing a published prompt appends a snapshotted version and keeps it published', function () {
    seedToolRegistry();
    $owner = User::factory()->create();
    $prompt = Prompt::factory()->for($owner, 'creator')->hasVersion()->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);

    $this->actingAs($owner)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor for validation.',
            'category_id' => $prompt->category_id,
            'type' => $prompt->type,
            'body' => 'Brand new body text for version two with a {{variable}} inside.',
            'tags' => 'updated, tags',
            'recommended_tools' => ['ChatGPT'],
            'price_npr' => 0,
            'visibility' => 'public',
            'changelog' => 'v2 content',
        ])
        ->assertRedirect();

    $prompt->refresh();
    expect($prompt->status)->toBe(Prompt::STATUS_PUBLISHED); // published stays published

    $latest = $prompt->latestVersion;
    expect($latest->version_number)->toBe(2)
        ->and($latest->body)->toBe('Brand new body text for version two with a {{variable}} inside.')
        ->and($latest->variables)->toBe(['variable'])
        ->and($latest->tools)->toBe(['ChatGPT'])
        ->and($latest->hasSnapshot())->toBeTrue();
});

test('an open report flips to pending after a live edit', function () {
    seedToolRegistry();
    $owner = User::factory()->create();
    $prompt = Prompt::factory()->for($owner, 'creator')->hasVersion()->create(['status' => Prompt::STATUS_PUBLISHED]);

    $report = \App\Models\PromptReport::factory()->create([
        'prompt_id' => $prompt->id,
        'status' => \App\Models\PromptReport::STATUS_OPEN,
    ]);

    $this->actingAs($owner)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor for validation.',
            'category_id' => $prompt->category_id,
            'type' => $prompt->type,
            'body' => 'Edited body after the report was filed, quite long enough.',
            'tags' => 'tag',
            'recommended_tools' => ['ChatGPT'],
            'price_npr' => 0,
            'visibility' => 'public',
        ])
        ->assertRedirect();

    expect($report->refresh()->status)->toBe('pending');
});

test('restore appends a copy as the newest version without mutating history', function () {
    seedToolRegistry();
    $owner = User::factory()->create();
    $prompt = Prompt::factory()->for($owner, 'creator')->hasVersion()->create();

    $v1 = $prompt->latestVersion;

    $this->actingAs($owner)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor for validation.',
            'category_id' => $prompt->category_id,
            'type' => $prompt->type,
            'body' => 'The second version body, quite different from the first.',
            'tags' => 'v2',
            'recommended_tools' => ['ChatGPT'],
            'price_npr' => 0,
            'visibility' => 'public',
        ])
        ->assertRedirect();

    $v1->forceFill(['variables' => ['topic'], 'tools' => ['ChatGPT', 'Claude']])->save();

    $this->actingAs($owner)
        ->post(route('prompts.versions.restore', [$prompt, $v1->id]))
        ->assertRedirect();

    $latest = $prompt->latestVersion()->first();
    expect($latest->version_number)->toBe(3)
        ->and($latest->body)->toBe($v1->body)
        ->and($latest->tools)->toBe(['ChatGPT', 'Claude'])
        ->and($latest->changelog)->toContain('Restored from v1');

    // History untouched: v1 row still holds its original body.
    expect($v1->refresh()->body)->toBe($v1->body);
});

test('versions page shows snapshots to the owner and honesty chips for pre-v1.5.0 rows', function () {
    $owner = User::factory()->create();
    // Priced: the paywall (metadata-only for non-holders) is a paid-only rule.
    $prompt = Prompt::factory()->for($owner, 'creator')->hasVersion()->priced(29900)->create();

    // Pre-v1.5.0 row: null body.
    DB::table('prompt_versions')->where('prompt_id', $prompt->id)->update(['body' => null]);

    $html = $this->actingAs($owner)->get(route('prompts.versions', $prompt))->getContent();
    expect($html)->toContain('Snapshot not captured before v1.5.0');

    // A fresh snapshotted edit renders its body for the owner...
    seedToolRegistry();
    $this->actingAs($owner)
        ->put(route('dashboard.prompts.update', $prompt), [
            'title' => $prompt->title,
            'description' => 'A deliberately long description that always clears the forty character floor for validation.',
            'category_id' => $prompt->category_id,
            'type' => $prompt->type,
            'body' => 'Owner-only snapshot body marker xyzzysnapshot.',
            'tags' => 'tag',
            'recommended_tools' => ['ChatGPT'],
            'price_npr' => 299,
            'visibility' => 'public',
        ])->assertRedirect();

    $this->actingAs($owner)->get(route('prompts.versions', $prompt))
        ->assertOk()
        ->assertSee('xyzzy'.'snapshot', false);

    // ...but never for a guest (metadata-only rows for non-holders).
    // actingAs persists across requests in a test — drop the guard first.
    $this->app['auth']->forgetGuards();

    $guest = $this->get(route('prompts.versions', $prompt))->getContent();
    expect($guest)->not->toContain('xyzzy'.'snapshot');
});

test('backfill writes variables/tools onto latest rows and never fabricates bodies', function () {
    $prompt = Prompt::factory()->hasVersion()->create();
    $latest = $prompt->latestVersion;

    // Simulate a pre-v1.5.0 row: no snapshot columns at all.
    DB::table('prompt_versions')->where('id', $latest->id)->update(['body' => null, 'variables' => null, 'tools' => null]);

    $this->artisan('pv:backfill-version-snapshots', ['--dry-run' => true])->assertExitCode(0);
    expect($latest->refresh()->variables)->toBeNull(); // dry run wrote nothing

    $this->artisan('pv:backfill-version-snapshots', ['--force' => true])->assertExitCode(0);
    $latest->refresh();

    // Tools recovered from the row's own recommended_tools; variables are
    // [] (nothing derivable from a null body); body NEVER fabricated.
    expect($latest->variables)->toBe([])
        ->and($latest->tools)->toBe(['ChatGPT', 'Claude'])
        ->and($latest->body)->toBeNull()
        ->and($latest->hasSnapshot())->toBeFalse();

    // Idempotent.
    $this->artisan('pv:backfill-version-snapshots', ['--force' => true])->assertExitCode(0);
});
