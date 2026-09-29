<?php

use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can switch into an account and return', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $member))
        ->assertRedirect(route('home'));

    expect(auth()->user()->id)->toBe($member->id);

    // Chrome bar data: the session remembers the real driver.
    expect(session('impersonator_id'))->toBe($admin->id);

    $row = Impersonation::query()->latest('id')->first();
    expect($row->impersonator_id)->toBe($admin->id)
        ->and($row->target_id)->toBe($member->id)
        ->and($row->ended_at)->toBeNull();

    $this->post(route('impersonation.stop'))
        ->assertRedirect(route('admin.users.index'));

    expect(auth()->user()->id)->toBe($admin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();

    $row->refresh();
    expect($row->ended_at)->not->toBeNull();
});

test('members and moderators cannot start an impersonation', function () {
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $mod = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $target = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $this->actingAs($member)
        ->post(route('admin.users.impersonate', $target))
        ->assertForbidden();

    $this->actingAs($mod)
        ->post(route('admin.users.impersonate', $target))
        ->assertForbidden();

    expect(Impersonation::query()->count())->toBe(0);
});

test('self-switch writes a closed no-op row and does not switch', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.users.impersonate', $admin))
        ->assertRedirect();

    expect(auth()->user()->id)->toBe($admin->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();

    $row = Impersonation::query()->latest('id')->first();
    expect($row->impersonator_id)->toBe($admin->id)
        ->and($row->target_id)->toBe($admin->id)
        ->and($row->ended_at)->not->toBeNull();
});

test('nested impersonation is refused', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $first))->assertRedirect();
    $this->post(route('admin.users.impersonate', $second))->assertForbidden();

    expect(Impersonation::query()->where('target_id', $second->id)->count())->toBe(0);
});

test('a stale impersonation entry self-heals', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $member = User::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $member))->assertRedirect();

    // Original driver loses admin mid-session.
    $admin->forceFill(['role' => User::ROLE_MEMBER])->save();

    $this->get(route('home')); // middleware runs on any web route

    expect(session()->has('impersonator_id'))->toBeFalse();
});
