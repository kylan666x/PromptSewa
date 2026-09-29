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
