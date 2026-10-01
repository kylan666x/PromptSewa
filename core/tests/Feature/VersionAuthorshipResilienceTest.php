<?php

use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * H2 (v1.5.2) — version authorship resilience.
 *
 * After adoption, original seeded authors hold no prompts. The pre-H2
 * cascade FK meant deleting such a user would wipe the ENTIRE version
 * history of every adopted listing. Now: the FK nulls on delete, history
 * survives, and the page renders the honest "Former creator" chip.
 */
test('force-deleting a version author keeps history alive with a Former creator chip', function () {
    $owner = User::factory()->create(); // owns the listing (post-adoption: the official account)
    $author = User::factory()->create(); // authored a version, owns nothing
    $viewer = User::factory()->create();

    $prompt = Prompt::factory()->for($owner, 'creator')->create();

    $prompt->versions()->create([
        'version_number' => 1,
        'body' => 'Original body text of version one.',
        'changelog' => 'Initial release.',
        'user_id' => $author->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    // Sanity: page renders with the real author first.
    $this->actingAs($viewer)
        ->get(route('prompts.versions', $prompt))
        ->assertOk()
        ->assertSee($author->name);

    // The destructive act: delete the author outright.
    $author->forceDelete();

    // History survived: the row is intact, authorship nulled.
    $version = PromptVersion::query()->sole();
    expect($version->user_id)->toBeNull()
        ->and($version->body)->toBe('Original body text of version one.');

    // The page is a 200 and shows the honest chip — no crash, no blank row.
    $this->actingAs($viewer)
        ->get(route('prompts.versions', $prompt))
        ->assertOk()
        ->assertSee('Former creator');
});

test('the FK on prompt_versions.user_id nulls on delete instead of cascading', function () {
    // PRAGMA truth, not DDL string matching: the column must be nullable
    // and its FK action must be SET NULL (never CASCADE).
    $column = collect(DB::select('pragma table_info(prompt_versions)'))->firstWhere('name', 'user_id');

    expect($column)->not->toBeNull()
        ->and((int) $column->notnull)->toBe(0);

    $fk = collect(DB::select('pragma foreign_key_list(prompt_versions)'))->firstWhere('from', 'user_id');

    expect($fk)->not->toBeNull()
        ->and($fk->on_delete)->toBe('SET NULL');
});

test('adopted authorship still renders when the original author account is purged', function () {
    // Simulate the exact post-adoption shape: official account owns the
    // listing, the original seeded author exists only in version rows.
    $official = User::factory()->create(['username' => 'promptsewa', 'is_official' => true, 'role' => User::ROLE_ADMIN]);
    $original = User::factory()->create(['email' => 'maya@promptsewa.test']);

    $prompt = Prompt::factory()->for($official, 'creator')->create();

    $prompt->versions()->create([
        'version_number' => 1,
        'body' => 'Adopted history body.',
        'changelog' => 'Initial release.',
        'user_id' => $original->id,
        'status' => PromptVersion::STATUS_PUBLISHED,
    ]);

    // The founder runs the demo purge from the admin panel.
    $this->artisan('pv:purge-demo', ['--force' => true])->assertExitCode(0);

    // Listing + history both survive, authorship honest.
    expect($prompt->refresh()->user_id)->toBe($official->id)
        ->and(PromptVersion::query()->count())->toBe(1);

    $this->get(route('prompts.versions', $prompt))
        ->assertOk()
        ->assertSee('Former creator');
});
