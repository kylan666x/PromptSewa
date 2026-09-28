<?php

use App\Http\Middleware\RejectBannedUsers;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function banAdmin(): User
{
    return User::factory()->create(['role' => User::ROLE_ADMIN]);
}

test('admin can ban and unban an account', function () {
    $admin = banAdmin();
    $user = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($admin)
        ->patch(route('admin.users.banned', $user))
        ->assertRedirect();

    expect($user->refresh()->isBanned())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.banned', $user))
        ->assertRedirect();

    expect($user->refresh()->isBanned())->toBeFalse();
});

test('moderators cannot ban accounts', function () {
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $user = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($mod)
        ->patch(route('admin.users.banned', $user))
        ->assertForbidden();

    expect($user->refresh()->isBanned())->toBeFalse();
});

test('an admin cannot ban their own account', function () {
    $admin = banAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.users.banned', $admin))
        ->assertSessionHasErrors('banned_at');

    expect($admin->refresh()->isBanned())->toBeFalse();
});

test('banned users are logged out on their next request', function () {
    $user = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $user->forceFill(['banned_at' => now()])->save();

    // Banned users get bounced off authenticated routes even if a session
    // cookie survives the ban.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('banning keeps content but blocks access', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();

    $creator->forceFill(['banned_at' => now()])->save();

    // Content stays live (bans are an access decision, not destruction)…
    $this->get(route('prompts.show', $prompt))->assertOk();

    // …but the account cannot act.
    $this->actingAs($creator)->get(route('dashboard'))->assertRedirect(route('home'));
    $this->assertGuest();
});

test('banned middleware runs before the staff gate too', function () {
    $bannedMod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $bannedMod->forceFill(['banned_at' => now()])->save();

    $this->actingAs($bannedMod)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
