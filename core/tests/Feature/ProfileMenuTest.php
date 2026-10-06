<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * F2 (v1.9.2) — "My Profile" in the avatar menus.
 *
 * The founder's report: the avatar menu had no direct door to the user's own
 * public profile. There are TWO avatar menus in the product — the
 * profile-picture caret on your own profile hero (`x-avatar-menu`) and the
 * navbar account dropdown — and both must offer it, pointing at
 * `/creators/{username}` (the username binding, not the numeric id).
 *
 * These lock the SERVED HTML: the entries are server-rendered, so no-JS
 * readers and crawlers see the same links a browser does.
 */
uses(RefreshDatabase::class);

test('the profile-picture menu links to the owner’s own public profile', function () {
    $user = User::factory()->create(['username' => 'menuowner']);

    $html = $this->actingAs($user)
        ->get(route('creators.show', $user))
        ->assertOk()
        ->getContent();

    // The entry exists, is labelled, and points at the username route…
    expect($html)->toContain('data-testid="avatar-my-profile"')
        ->and($html)->toContain('My Profile')
        ->and($html)->toContain('href="'.route('creators.show', $user).'"');

    // …which is the /creators/{username} URL, not an id.
    expect(route('creators.show', $user))->toContain('/creators/menuowner');

    // The two S2 actions are still there, unchanged.
    expect($html)->toContain('data-testid="avatar-upload-edit"')
        ->and($html)->toContain('data-testid="avatar-edit-frame"');
});

test('the navbar account dropdown offers My profile too', function () {
    $user = User::factory()->create(['username' => 'navowner']);

    $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('data-testid="nav-view-profile"')
        ->and($html)->toContain('href="'.route('creators.show', $user).'"');
});

test('a stranger’s profile never renders the owner’s menu or its profile link', function () {
    $owner = User::factory()->create(['username' => 'privateowner']);
    $stranger = User::factory()->create();

    $html = $this->actingAs($stranger)
        ->get(route('creators.show', $owner))
        ->assertOk()
        ->getContent();

    // The owner-only component is absent, and nothing on a stranger's page
    // renders its "My Profile" entry. (The owner's URL itself legitimately
    // appears in the canonical/JSON-LD head, so the URL is not the signal —
    // the MENU is.)
    expect($html)->not->toContain('data-testid="avatar-my-profile"')
        ->and($html)->not->toContain('data-testid="own-avatar-menu"')
        ->and($html)->not->toContain('My Profile');
});
