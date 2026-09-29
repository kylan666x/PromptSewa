<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------- T1

test('pv:official-account creates the house account idempotently', function () {
    $this->artisan('pv:official-account')->assertExitCode(0);

    $official = User::query()->where('username', 'promptsewa')->first();
    expect($official)->not->toBeNull()
        ->and($official->role)->toBe(User::ROLE_ADMIN)
        ->and($official->is_verified)->toBeTrue()
        ->and($official->is_official)->toBeTrue();

    // Second run flags rather than duplicates.
    $this->artisan('pv:official-account')->assertExitCode(0);
    expect(User::query()->where('username', 'promptsewa')->count())->toBe(1);
});

test('the official account profile can only be edited by admins', function () {
    // Edge case exercising the guard directly: an official-flagged account
    // held by a NON-admin session (role drift, or founder handed creds to
    // a moderator) must be locked out of profile editing.
    $officialNonAdmin = User::factory()->create([
        'username' => 'promptsewa',
        'role' => User::ROLE_MODERATOR,
        'is_official' => true,
    ]);

    $this->actingAs($officialNonAdmin)->get(route('dashboard.profile.edit'))->assertForbidden();
    $this->actingAs($officialNonAdmin)->put(route('dashboard.profile.update'), [
        'name' => 'Hacked Name',
        'username' => 'promptsewa',
    ])->assertForbidden();

    expect($officialNonAdmin->refresh()->name)->not->toBe('Hacked Name');

    // An admin-owned official account edits its own profile freely.
    $officialAdmin = User::factory()->create([
        'username' => 'promptsewa-two',
        'role' => User::ROLE_ADMIN,
        'is_official' => true,
    ]);

    $this->actingAs($officialAdmin)->put(route('dashboard.profile.update'), [
        'name' => 'PromptSewa',
        'username' => 'promptsewa-two',
    ])->assertRedirect();

    expect($officialAdmin->refresh()->name)->toBe('PromptSewa');
});

// ---------------------------------------------------------------- T2

test('the official badge wins over the verified seal on every surface', function () {
    $official = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'is_verified' => true,
        'is_official' => true,
    ]);

    $prompt = Prompt::factory()->for($official, 'creator')->create([
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
        'price_cents' => 0,
    ]);

    $html = $this->get(route('prompts.show', $prompt))->getContent();
    expect($html)->toContain('#1d9bf0');
});

test('typeahead carries the badge field with official winning', function () {
    // searchCreators only surfaces users with ≥1 public prompt.
    $house = User::factory()->create([
        'username' => 'house-official',
        'is_verified' => true,
        'is_official' => true,
    ]);
    $plain = User::factory()->create([
        'username' => 'plainverified',
        'is_verified' => true,
    ]);

    Prompt::factory()->for($house, 'creator')->create();
    Prompt::factory()->for($plain, 'creator')->create();

    $json = $this->getJson(route('search.preview', ['q' => 'house']))->assertOk()->json();
    $officialHit = collect($json['creators'])->firstWhere('username', 'house-official');
    expect($officialHit['badge'])->toBe('official');

    $json2 = $this->getJson(route('search.preview', ['q' => 'plain']))->assertOk()->json();
    $verifiedHit = collect($json2['creators'])->firstWhere('username', 'plainverified');
    expect($verifiedHit['badge'])->toBe('verified');
});
