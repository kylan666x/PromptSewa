<?php

use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * G3 (v1.7.4) — mobile engagement pass.
 *
 * The engagement patterns (scroll-snap rail, carousel, chips) are CSS-only:
 * zero new dependencies, and every pattern carries an md: guard so the
 * desktop rhythm is unchanged. These tests assert the RENDERED markup —
 * a snap rail that silently loses its md: guard would wrap the desktop
 * layout into one column and nobody would notice without a screenshot.
 */
uses(RefreshDatabase::class);

test('the hero type scale steps down below md', function () {
    $html = $this->get(route('home'))->getContent();

    // text-3xl → sm:text-5xl → md:text-6xl (never a single huge size).
    expect($html)->toContain('text-3xl font-bold tracking-tight text-ink sm:mt-6 sm:text-5xl md:text-6xl');
});

test('the hero shows one primary CTA with a demoted text link on mobile', function () {
    $html = $this->get(route('home'))->getContent();

    // Primary pill is full-width on phones, auto from md.
    expect($html)->toContain('w-full rounded-full bg-saffron px-6 py-3')
        ->and($html)->toContain('md:w-auto');

    // The secondary action is a text link on mobile, a pill from md up.
    expect($html)->toContain('text-sm font-semibold text-ink/70 underline')
        ->and($html)->toContain('md:rounded-full md:border');
});

test('the trending chips are a keyboard-reachable snap rail that un-rails on desktop', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-2')
        ->and($html)->toContain('md:flex-wrap md:justify-center md:overflow-visible')
        // The rail is focusable so keyboard users can scroll it.
        ->and($html)->toContain('aria-label="Trending searches" tabindex="0"');
});

test('Fresh from the library is a carousel below md and a grid from md up', function () {
    cache()->flush();

    $creator = User::factory()->create();
    Prompt::factory()->published()->for($creator, 'creator')->create();

    $html = $this->get(route('home'))->getContent();

    // Same DOM for both layouts — the wrapper only changes behaviour.
    // (Token-level assertions: asserting one long class substring would
    // break every time a responsive utility is inserted mid-string.)
    expect($html)->toContain('flex snap-x snap-mandatory gap-4 overflow-x-auto')
        ->and($html)->toContain('md:grid md:grid-cols-2')
        ->and($html)->toContain('md:overflow-visible')
        ->and($html)->toContain('lg:grid-cols-3')
        ->and($html)->toContain('w-[82%] shrink-0 snap-start sm:w-[60%] md:w-auto');
});

test('the stats band is three mono chips on mobile and the inkwell strip on desktop', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('min-w-[10rem] shrink-0 snap-start rounded-2xl bg-ink')
        ->and($html)->toContain('md:grid md:grid-cols-3 md:gap-4 md:overflow-visible');
});

test('the image gallery is two columns with 4:5 covers on phones', function () {
    cache()->flush();

    $creator = User::factory()->create();
    Prompt::factory()->published()->for($creator, 'creator')->create(['type' => Prompt::TYPE_IMAGE]);

    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3 lg:grid-cols-4')
        ->and($html)->toContain('aspect-[4/5]');
});

test('section headers keep their right-aligned arrow links', function () {
    cache()->flush();

    $creator = User::factory()->create();
    Prompt::factory()->published()->for($creator, 'creator')->create(['type' => Prompt::TYPE_IMAGE]);
    Category::factory()->create();

    $html = $this->get(route('home'))->getContent();

    // ml-auto + self-end keeps the link hard right when the header stacks.
    expect(substr_count($html, 'ml-auto shrink-0 self-end'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('Browse all &rarr;');
});

test('the dashboard tab row scrolls horizontally below md', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('role="navigation" aria-label="Dashboard sections" tabindex="0"')
        ->and($html)->toContain('flex w-full snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-1')
        ->and($html)->toContain('sm:w-auto')
        ->and($html)->toContain('sm:flex-wrap')
        ->and($html)->toContain('sm:overflow-visible');
});

test('the dashboard stat cards are two-up on phones and full-width sparklines', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard', ['tab' => 'stats']))->getContent();

    expect($html)->toContain('grid grid-cols-2 gap-3 md:grid-cols-1 md:gap-6 lg:grid-cols-2')
        // The first card spans both phone columns so its sparkline is full-width.
        ->and($html)->toContain('col-span-2 rounded-2xl border border-ink/10 bg-white p-4 shadow-sm md:p-6');
});

test('the engagement pass adds no new dependencies or scrollbar-hiding hacks', function () {
    $html = $this->get(route('home'))->getContent();

    // Native scroll-snap only: no scrollbar suppression, no JS scroll driver.
    expect($html)->not->toContain('scrollbar-hide')
        ->and($html)->not->toContain('::-webkit-scrollbar')
        ->and($html)->not->toContain('no-scrollbar');

    // The viewport meta must keep user-scalable (WCAG): never maximum-scale.
    expect($html)->toContain('width=device-width, initial-scale=1');
});