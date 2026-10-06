<?php

use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S3 (v1.9.0) — the membership storefront's entry points.
 *
 * Locks: /memberships is reachable by guests and signed-in users, the
 * account dropdown carries the Memberships link (the mobile dock keeps its
 * exactly-five-slot contract — that lock stays with MobileDockTest), the
 * owner's own profile carries the second door where mobile's You slot
 * lands, and the Sikka desk's Plans tab previews the same storefront.
 */
function membershipAccessPlan(): MembershipPlan
{
    return MembershipPlan::factory()->create([
        'name' => 'Access plan',
        'slug' => 'access-plan',
        'duration_days' => 30,
        'price_paisa' => 49_900,
        'stipend_sikka' => 10,
        'active' => true,
    ]);
}

test('the storefront is reachable and the signed-in account menu carries the link', function () {
    membershipAccessPlan();

    // Guests browse plans without an account.
    $this->get(route('memberships.index'))->assertOk()->assertSee('Access plan');

    $buyer = User::factory()->create();

    $html = $this->actingAs($buyer)->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('data-testid="nav-memberships"')
        ->and($html)->toContain(route('memberships.index'));

    // A guest navbar has no account menu at all.
    $this->app->make('auth')->guard('web')->forgetUser();

    $guest = $this->get(route('home'))->assertOk()->getContent();

    expect($guest)->not->toContain('data-testid="nav-memberships"');
});

test('the owner profile carries the memberships door and strangers never see it', function () {
    membershipAccessPlan();

    $owner = User::factory()->create(['role' => User::ROLE_CREATOR]);

    $own = $this->actingAs($owner)->get(route('creators.show', $owner))->assertOk()->getContent();

    expect($own)->toContain('data-testid="profile-memberships"')
        ->and($own)->toContain(route('memberships.index'));

    $stranger = User::factory()->create();

    $other = $this->actingAs($stranger)->get(route('creators.show', $owner))->assertOk()->getContent();

    expect($other)->not->toContain('data-testid="profile-memberships"');
});

test('the Sikka desk Plans tab previews the storefront', function () {
    membershipAccessPlan();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $desk = $this->actingAs($admin)->get(route('admin.sikka.index'))->assertOk()->getContent();

    expect($desk)->toContain('data-testid="sikka-plans-storefront"')
        ->and($desk)->toContain('View storefront')
        ->and($desk)->toContain(route('memberships.index'));

    // The preview target is the live storefront.
    $this->actingAs($admin)->get(route('memberships.index'))->assertOk()->assertSee('Access plan');
});
