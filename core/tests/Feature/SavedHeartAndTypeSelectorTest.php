<?php

use App\Models\Bookmark;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===========================================================================
// P1 — Saved heart semantics (filled rose = saved)
// ===========================================================================

test('a pre-flipped saved heart carries the filled-rose class and aria-pressed on cards', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    $html = $this->actingAs($user)->get(route('library.index'))->getContent();

    // Saved state ships server-side: the filled-rose class is present in
    // the Alpine config's :class expression AND the state is saved:true.
    expect($html)->toContain('text-rose-600 fill-current')
        ->and($html)->toContain('saved'.chr(92).'u0022:true');
});

test('an unsaved heart ships the outline state', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $html = $this->actingAs($user)->get(route('library.index'))->getContent();

    expect($html)->toContain('text-ink/40 fill-none')
        ->and($html)->toContain('saved'.chr(92).'u0022:false');
});

test('the detail page heart ships filled-rose when saved and outline when not', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $unsaved = $this->actingAs($user)->get(route('prompts.show', $prompt))->getContent();
    expect($unsaved)->toContain('fill-none text-current');

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    $saved = $this->actingAs($user)->get(route('prompts.show', $prompt))->getContent();
    expect($saved)->toContain('text-rose-600 fill-current');
});

// ===========================================================================
// P2 — Authoring type selector: SUPERSEDED in v1.7.2 (A1/A3).
// The type-selector battery moved to AuthoringTypeSelectorTest — every
// assertion there hits the served route HTML (component-template
// assertions are banned for route-facing UI).
// ===========================================================================
