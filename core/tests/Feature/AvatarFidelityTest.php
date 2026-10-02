<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * C1 (v1.4.4) — avatar fidelity.
 *
 * Every creator-identity surface renders the real profile photo when
 * avatar_path exists and the deterministic initials badge when null.
 * One shared helper asserts all surfaces; alt must carry the handle and
 * no src="" may ever render.
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function withAvatar(User $user): User
{
    Storage::disk('public')->put('avatars/test-avatar.jpg', 'fake-image-bytes');
    $user->forceFill(['avatar_path' => 'avatars/test-avatar.jpg'])->save();

    return $user->refresh();
}

function assertAvatarSurface($response, User $user, string $expectedUrl): void
{
    $html = $response->getContent() ?? '';

    // NOTE (Pest v3): toContain() takes ...$needles — a second "message"
    // argument would be treated as an ADDITIONAL needle (the v1.4.2
    // RenderedFormOrderTest gotcha). No message args here.
    if ($user->avatar_path) {
        expect($html)->toContain($expectedUrl)
            ->and($html)->toContain('alt="@'.($user->username ?? $user->name).'"')
            ->and($html)->not->toContain('src=""');
    } else {
        expect($html)->not->toContain($expectedUrl)
            ->and($html)->toContain(mb_substr($user->name, 0, 1));
    }
}

test('prompt card shows the real photo when the creator has an avatar', function () {
    $creator = User::factory()->create(['name' => 'Ava Photo']);
    withAvatar($creator);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $response = $this->get(route('home'));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url($creator->avatar_path));
});

test('prompt card falls back to the initials badge when there is no avatar', function () {
    $creator = User::factory()->create(['name' => 'Bob Initials']);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $response = $this->get(route('home'));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url('avatars/nope.jpg'));
});

test('prompt detail byline renders the avatar image', function () {
    $creator = User::factory()->create(['name' => 'Ava Byline']);
    withAvatar($creator);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $response = $this->get(route('prompts.show', $prompt));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url($creator->avatar_path));
});

test('library creators grid renders the avatar image', function () {
    // The creators grid only lists users with a published prompt.
    $creator = User::factory()->create(['name' => 'Ava Grid', 'username' => 'avagrid']);
    Prompt::factory()->published()->for($creator, 'creator')->create();
    withAvatar($creator);

    $response = $this->get(route('library.index', ['q' => 'Ava']));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url($creator->avatar_path));
});

test('versions page author row renders the avatar image', function () {
    $creator = User::factory()->create(['name' => 'Ava Versions']);
    withAvatar($creator);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $response = $this->get(route('prompts.versions', $prompt));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url($creator->avatar_path));
});

test('admin users table renders the avatar image', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $subject = User::factory()->create(['name' => 'Ava Admin']);
    withAvatar($subject);

    $response = $this->actingAs($admin)->get(route('admin.users.index'));
    $response->assertOk();

    assertAvatarSurface($response, $subject, Storage::disk('public')->url($subject->avatar_path));
});

test('navbar dropdown header renders the logged-in avatar', function () {
    $user = User::factory()->create(['name' => 'Ava Nav']);
    withAvatar($user);

    $response = $this->actingAs($user)->get(route('home'));
    $response->assertOk();

    assertAvatarSurface($response, $user, Storage::disk('public')->url($user->avatar_path));
});

test('typeahead JSON carries the avatar url for creators', function () {
    // searchCreators only returns users with a published prompt — give the
    // creator one or the typeahead comes back empty.
    $creator = User::factory()->create(['name' => 'Ava Typeahead', 'username' => 'avatype']);
    Prompt::factory()->published()->for($creator, 'creator')->create();
    withAvatar($creator);

    $response = $this->getJson(route('search.preview', ['q' => 'Ava']));
    $response->assertOk();

    $creators = $response->json('creators');
    expect($creators)->toHaveCount(1)
        ->and($creators[0]['avatar_url'])->toBe(Storage::disk('public')->url($creator->avatar_path))
        ->and($creators[0]['initial'])->toBe('A');
});

test('typeahead JSON carries null avatar url when none is set', function () {
    $creator = User::factory()->create(['name' => 'Nude Typehead', 'username' => 'noavatar']);
    Prompt::factory()->published()->for($creator, 'creator')->create();

    $response = $this->getJson(route('search.preview', ['q' => 'Nude']));
    $response->assertOk();

    expect($response->json('creators.0.avatar_url'))->toBeNull();
});

test('creator profile hero uses the shared component', function () {
    $creator = User::factory()->create(['name' => 'Ava Hero', 'username' => 'avahero']);
    withAvatar($creator);

    $response = $this->get(route('creators.show', $creator));
    $response->assertOk();

    assertAvatarSurface($response, $creator, Storage::disk('public')->url($creator->avatar_path));
});

// ---------------------------------------------------------------------------
// G1 (v1.7.4) — frame-outside-circle geometry additions
// ---------------------------------------------------------------------------

test('every avatar surface clips the PHOTO, never the frame', function () {
    // §6.35: the database cache store SURVIVES RefreshDatabase — flush or a
    // previous test's cached page 1 shadows this fixture.
    cache()->flush();

    $creator = User::factory()->create(['name' => 'Clip G1', 'username' => 'clipg1']);
    withAvatar($creator);
    Prompt::factory()->published()->for($creator, 'creator')->create();

    $surfaces = [
        'hero' => route('creators.show', $creator),
        'library' => route('library.index', ['q' => 'Clip']),
        'home' => route('home'),
    ];

    foreach ($surfaces as $name => $url) {
        $html = $this->get($url)->getContent();

        // v1.7.5 composite: the circle is the PHOTO LAYER, which is
        // absolute + rounded-full + clipping and inset to the frame's hole.
        expect($html)->toMatch('/<span class="absolute overflow-hidden rounded-full/');

        // …and the wrapper (the composite box) does NOT clip or paint.
        preg_match('/<span class="relative inline-block isolate[^"]*"/', $html, $wrapper);
        expect($wrapper[1] ?? 'no-wrapper')->not->toContain('overflow-hidden')
            ->and($wrapper[1] ?? 'no-wrapper')->not->toContain('rounded-')
            ->and($wrapper[1] ?? 'no-wrapper')->not->toContain('bg-');
    }
});

test('the navbar account pill does not clip a framed avatar', function () {
    Storage::fake('public');
    $frame = App\Models\Frame::query()->create([
        'name' => 'Nav pill', 'image_path' => 'frames/nav.png', 'is_active' => true,
    ]);
    $creator = User::factory()->for($frame, 'activeFrame')->create();
    withAvatar($creator);

    $html = $this->actingAs($creator)->get(route('dashboard'))->getContent();

    // The pill wraps the avatar tightly — a clipping pill truncates the ring.
    preg_match('/<span class="flex size-8 shrink-0 items-center justify-center">/', $html, $pill);
    expect($pill[0] ?? 'no-pill')->not->toContain('overflow-hidden');
});

test('the typeahead row mirrors the composite box exactly', function () {
    $html = $this->get(route('home'))->getContent();

    // v1.7.5: the hand-rolled Alpine row uses the SAME tokens as
    // x-user-avatar — isolate wrapper, frame at inset-0, circle inset to the
    // frame's own hole (the value arrives pre-computed as frame_inset).
    expect($html)->toContain('relative inline-block isolate size-6 shrink-0')
        ->and($html)->toContain('pointer-events-none absolute inset-0 z-10 size-full object-contain')
        ->and($html)->toContain('absolute overflow-hidden rounded-full" :style="\'inset: \' + creator.frame_inset');
});


