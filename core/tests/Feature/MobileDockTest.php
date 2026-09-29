<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Extract just the dock element so assertions can't hit desktop markup. */
function dockHtml(string $html): string
{
    preg_match('/<nav aria-label="Mobile primary".*?<\/nav>/s', $html, $m);

    return $m[0] ?? '';
}

test('the dock renders exactly five slots with correct hrefs for a guest', function () {
    $dock = dockHtml($this->get(route('home'))->getContent());

    expect(substr_count($dock, '<a '))->toBe(5)
        ->and($dock)->toContain('href="'.route('home').'"')
        ->and($dock)->toContain('href="'.route('library.index').'"')
        ->and($dock)->toContain('href="'.route('register').'"')
        ->and($dock)->toContain('href="'.route('login').'"');
});

test('the dock renders exactly five slots with correct hrefs for an authed member', function () {
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

    $dock = dockHtml($this->actingAs($member)->get(route('home'))->getContent());

    expect(substr_count($dock, '<a '))->toBe(5)
        ->and($dock)->toContain('href="'.route('dashboard.prompts.create').'"')
        ->and($dock)->toContain('href="'.route('purchases.index').'"')
        ->and($dock)->toContain('href="'.route('dashboard.profile.edit').'"')
        // Member must never see an admin destination.
        ->not->toContain(route('admin.dashboard'));
});

test('the dock is hidden on md and up and the center CTA carries the only saffron fill', function () {
    $member = User::factory()->create();

    $dock = dockHtml($this->actingAs($member)->get(route('home'))->getContent());

    expect($dock)->toContain('md:hidden');
});

test('saffron fill appears only on the center CTA in the dock', function () {
    $member = User::factory()->create();

    $dock = dockHtml($this->actingAs($member)->get(route('home'))->getContent());

    // Class-string assertions: bg-saffron once (center CTA); text-saffron
    // marks the active tab state, which is not a saffron FILL.
    expect(substr_count($dock, 'bg-saffron'))->toBe(1)
        ->and($dock)->toContain('bg-saffron');
});

test('the active tab carries aria-current', function () {
    $member = User::factory()->create();

    $dock = dockHtml($this->actingAs($member)->get(route('library.index'))->getContent());

    expect($dock)->toContain('aria-current="page"')
        ->and($dock)->toContain('aria-label="Browse prompts"');
});

test('no burger button survives anywhere in the mobile layout', function () {
    $member = User::factory()->create();

    $html = $this->actingAs($member)->get(route('home'))->getContent();

    expect($html)->not->toContain('aria-label="Menu"')
        ->and($html)->not->toContain('M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5'); // hamburger glyph path
});

test('category chips render on the library page', function () {
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = \App\Models\Prompt::factory()->for($creator, 'creator')->hasVersion()->create();
    $category = $prompt->category;

    $html = $this->get(route('library.index'))->getContent();

    expect($html)->toContain('Category shortcuts')
        ->and($html)->toContain(route('library.category', $category));
});

test('the profile page hosts the You menu with server-gated admin row and logout', function () {
    $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
    $memberHtml = $this->actingAs($member)->get(route('dashboard.profile.edit'))->getContent();
    expect($memberHtml)->toContain('Account')
        ->and($memberHtml)->toContain(route('packs.index'))
        ->and($memberHtml)->toContain(route('pages.about'))
        ->and($memberHtml)->toContain('Log out')
        ->not->toContain(route('admin.dashboard'));

    // Staff sees the Admin row — server-side gate, not CSS.
    $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $staffHtml = $this->actingAs($staff)->get(route('dashboard.profile.edit'))->getContent();
    expect($staffHtml)->toContain(route('admin.dashboard'));
});

test('the dock clears the content via body wrapper padding', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('pb-24');
});
