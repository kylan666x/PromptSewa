<?php

use App\Models\Frame;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * F2 (v1.7.8) — BH-R9-02: the frame round-trip + the founder's
 * profile-picture click menu. S2 (v1.9.0) trimmed it to two actions;
 * F2 (v1.9.2) adds the founder's "My Profile" entry back on top of them —
 * clicking the picture itself IS still the view action (lightbox), and the
 * caret opens My Profile + upload/edit + frame.
 *
 * The menu is server-rendered and owner-only BY CONSTRUCTION: the hero
 * avatar on your own public profile and the navbar account dropdown are the
 * only two homes, so a stranger's page physically cannot contain it. These
 * locks assert both directions from the SERVED HTML, plus the equip/change
 * round trip that keeps the composite overlay on hero + card + navbar, and
 * the int cast that makes the picker's strict pre-check driver-safe.
 */
test('the owner sees the profile-picture menu with My Profile and the direct lightbox door', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->get(route('creators.show', $user))
        ->assertOk()
        ->getContent();

    // Hero: the picture is the lightbox door; the caret opens the menu.
    expect($html)->toContain('data-testid="own-avatar-menu"')
        ->and($html)->toContain('data-testid="avatar-open-lightbox"')
        ->and($html)->toContain('data-testid="avatar-menu-toggle"')
        ->and($html)->toContain('data-testid="avatar-upload-edit"')
        ->and($html)->toContain('data-testid="avatar-edit-frame"')
        ->and($html)->toContain('data-testid="avatar-lightbox"')
        // F2 (v1.9.2): the menu regains a direct profile link.
        ->and($html)->toContain('data-testid="avatar-my-profile"')
        ->and($html)->toContain(route('creators.show', $user))
        // S2 (v1.9.0): "View profile picture" is no longer a MENU item —
        // the direct click replaced it.
        ->and($html)->not->toContain('data-testid="avatar-view-picture"')
        ->and($html)->toContain('My Profile')
        ->and($html)->toContain('Upload / edit picture')
        ->and($html)->toContain('Edit frame')
        // "Direct click still opens the lightbox": the served trigger
        // carries the Alpine open handler.
        ->and($html)->toContain('@click="lightbox = true"')
        // The two links must carry the anchors that make them work.
        ->and($html)->toContain(route('dashboard.profile.edit').'#avatar"')
        ->and($html)->toContain(route('dashboard.profile.edit').'#avatar-frame"');

    // Navbar: the picture opens the lightbox directly; the dropdown keeps
    // the two actions.
    expect($html)->toContain('data-testid="nav-avatar-view"')
        ->and($html)->toContain('data-testid="nav-avatar-upload"')
        ->and($html)->toContain('data-testid="nav-avatar-frame"');
});

test('strangers get no profile-picture menu — and guests get nothing at all', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    // A signed-in stranger: no hero menu on the owner's profile. (Their own
    // navbar entries exist — that is their own account menu, not the
    // owner's.) The hero menu testids are unique to this component.
    $html = $this->actingAs($stranger)
        ->get(route('creators.show', $owner))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('data-testid="own-avatar-menu"')
        ->and($html)->not->toContain('data-testid="avatar-open-lightbox"')
        ->and($html)->not->toContain('data-testid="avatar-upload-edit"')
        ->and($html)->not->toContain('data-testid="avatar-edit-frame"')
        ->and($html)->not->toContain('data-testid="avatar-my-profile"')
        // …while their own navbar menu is present (they are signed in).
        ->and($html)->toContain('data-testid="nav-avatar-view"');

    // A guest: neither the hero menu nor any navbar entries. actingAs state
    // leaks into the next request, so flush the guard first.
    $this->app->make('auth')->guard('web')->forgetUser();

    $guestHtml = $this->get(route('creators.show', $owner))
        ->assertOk()
        ->getContent();

    expect($guestHtml)->not->toContain('data-testid="own-avatar-menu"')
        ->and($guestHtml)->not->toContain('data-testid="nav-avatar-view"')
        ->and($guestHtml)->not->toContain('data-testid="avatar-lightbox"');
});

test('equipping a frame through the served profile form frames hero, cards and navbar', function () {
    $user = User::factory()->create();
    $frame = Frame::query()->create([
        'name' => 'Round Trip Ring',
        'image_path' => 'frames/round-trip.png',
        'is_active' => true,
        'criterion' => null, // free — anyone may equip it
        'hole_percent' => 62,
    ]);

    // A published prompt so the public profile renders at least one card.
    Prompt::factory()->for($user, 'creator')->withVersion()->create([
        'title' => 'Round Trip Prompt',
        'status' => Prompt::STATUS_PUBLISHED,
        'price_cents' => 19_900,
    ]);

    // Read the SERVED form's own values (browser semantics), not a
    // hand-built payload.
    $editHtml = $this->actingAs($user)->get(route('dashboard.profile.edit'))->assertOk()->getContent();

    preg_match('/name="name"[^>]*value="([^"]*)"/', $editHtml, $nameMatch);
    preg_match('/name="username"[^>]*value="([^"]*)"/', $editHtml, $usernameMatch);

    expect($nameMatch[1] ?? null)->not->toBeNull()
        ->and($usernameMatch[1] ?? null)->not->toBeNull();

    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $nameMatch[1],
        'username' => $usernameMatch[1],
        'active_frame_id' => $frame->id,
    ])->assertRedirect();

    expect($user->refresh()->active_frame_id)->toBe($frame->id);

    // The picker re-renders with the radio pre-checked…
    $afterEdit = $this->actingAs($user)->get(route('dashboard.profile.edit'))->getContent();
    expect($afterEdit)->toContain('value="'.$frame->id.'" checked');

    // …and every surface — hero, at least one card, navbar (both viewports)
    // — carries the composite overlay for the equipped frame.
    $profile = $this->actingAs($user)->get(route('creators.show', $user))->getContent();
    expect(substr_count($profile, 'data-frame-hole="62"'))->toBeGreaterThanOrEqual(
        3,
        'hero (xl) + profile card(s) + navbar must all render the frame overlay'
    );
    expect($profile)->toContain('inset: 19%'); // (100 - 62) / 2

    // Clearing works through the same served form (None radio = empty id).
    $this->actingAs($user)->put(route('dashboard.profile.update'), [
        'name' => $user->name,
        'username' => $user->username,
        'active_frame_id' => '',
    ])->assertRedirect();

    expect($user->refresh()->active_frame_id)->toBeNull();
});

test('active_frame_id is int-cast so the picker precheck survives string drivers', function () {
    $user = new User;
    $user->active_frame_id = '2';

    // Strict comparison in the picker: this must be an int even when the
    // driver (MySQL/PDO configurations) hands the attribute back as a
    // string, otherwise the equipped frame renders unchecked.
    expect($user->active_frame_id)->toBe(2);
});
