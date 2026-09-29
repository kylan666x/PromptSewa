<?php

use App\Models\Bookmark;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bookmark toggle saves and unsaves idempotently', function () {
    $user = User::factory()->create();
    $prompt = Prompt::factory()->for($user, 'creator')->hasVersion()->create();

    // Save.
    $this->actingAs($user)
        ->postJson(route('bookmarks.toggle', $prompt))
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect(Bookmark::query()->where('user_id', $user->id)->where('prompt_id', $prompt->id)->count())->toBe(1);

    // Idempotent POST while saved → stays exactly one row (toggle removes it).
    $this->actingAs($user)->postJson(route('bookmarks.toggle', $prompt))->assertOk();
    expect(Bookmark::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('a user can never touch another user bookmark state — 404 on invisible prompts', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    // A private listing is not viewable by the stranger → 404, no row.
    $secret = Prompt::factory()->for($owner, 'creator')->hasVersion()->private()->create();

    $this->actingAs($stranger)
        ->postJson(route('bookmarks.toggle', $secret))
        ->assertNotFound();

    expect(Bookmark::query()->count())->toBe(0);
});

test('the saved tab lists bookmarked prompts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    $this->actingAs($user)
        ->get(route('dashboard', ['tab' => 'saved']))
        ->assertOk()
        ->assertSee('Saved prompts')
        ->assertSee($prompt->title);
});

test('the prompt card heart is pre-flipped for an existing bookmark', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    $html = $this->actingAs($user)->get(route('library.index'))->getContent();

    // The heart's Alpine config carries saved:true for this card. Blade's
    // Js::from escapes quotes as \u0022 in the served HTML.
    expect($html)->toContain('bookmarkHeart')
        ->and($html)->toContain('saved'.chr(92).'u0022:true');

    // And without a bookmark it starts false.
    $other = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();
    $html2 = $this->actingAs($user)->get(route('library.index'))->getContent();
    expect($html2)->toContain('saved'.chr(92).'u0022:false');
});

test('the detail page shows the save button with the correct state', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $this->actingAs($user)->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee('Save for later');

    Bookmark::query()->create(['user_id' => $user->id, 'prompt_id' => $prompt->id]);

    $this->actingAs($user)->get(route('prompts.show', $prompt))
        ->assertOk()
        ->assertSee('Saved');
});
