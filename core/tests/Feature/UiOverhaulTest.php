<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * S4 (v1.9.0) — the Facebook/Instagram-style UI overhaul.
 *
 * The visual contract lives in two places and this file checks both:
 *
 *  1. the COMPILED stylesheet the app actually serves (resolved through
 *     public/build/manifest.json, never a guessed filename) — the S4 token
 *     set is worthless if it never reaches a browser; and
 *  2. the SERVED HTML of the four priority surfaces — the feed card's
 *     reading order, the two-column detail shell, the settings-style tab
 *     rail and the section rhythm are all structural facts a screenshot
 *     would show and a stylesheet alone cannot prove.
 *
 * The 360px contract is asserted as absence-of-base-columns: every
 * responsive grid on these surfaces is single-column until a `sm:`/`lg:`
 * guard turns it on, which is exactly what a phone viewport resolves to.
 *
 * Invariants this workstream must NOT break (each locked elsewhere, and
 * re-checked here so an S4 regression cannot land silently): the composite
 * box avatar geometry, the five-slot mobile dock, and Sikka-only buyer
 * pricing.
 */
uses(RefreshDatabase::class);

/** The compiled stylesheet the app serves, resolved via the Vite manifest. */
function uiOverhaulBuiltCss(): string
{
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true);
    $asset = $manifest['resources/css/app.css']['file'] ?? null;

    expect($asset)->not->toBeNull('built CSS asset missing — run npm run build');

    return (string) file_get_contents(dirname(__DIR__, 2).'/public/build/'.$asset);
}

/** A paid published prompt with a published v1, for the buyer surfaces. */
function uiOverhaulPaidPrompt(): Prompt
{
    $creator = User::factory()->create();

    return Prompt::factory()
        ->for($creator, 'creator')
        ->hasVersion()
        ->create(['price_cents' => 24_900, 'status' => Prompt::STATUS_PUBLISHED, 'visibility' => Prompt::VISIBILITY_PUBLIC]);
}

test('the S4 token set compiles into the stylesheet the app serves', function () {
    $css = uiOverhaulBuiltCss();

    // Warm-white paper ground.
    expect($css)->toContain('--color-paper:#faf8f3')
        // Inter carries body copy; Space Grotesk carries headings.
        ->and($css)->toContain('--font-sans:"Inter"')
        ->and($css)->toContain('--font-display:"Space Grotesk"')
        ->and($css)->toContain('h1,h2,h3,h4{font-family:var(--font-display)')
        // The card lift: a light resting shadow, a clear hover step.
        ->and($css)->toContain('shadow-card-hover{--tw-shadow:0 4px 6px -1px')
        ->and($css)->toContain('shadow-card{--tw-shadow:0 1px 2px 0');

    // The source of truth and the shipped artifact agree.
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    expect($source)->toContain('--color-paper: #faf8f3')
        ->and($source)->toContain("--font-sans: 'Inter'")
        ->and($source)->toContain("--font-display: 'Space Grotesk'");
});

test('every app surface loads Inter and Space Grotesk from the font CDN', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->toContain('family=Inter:wght@400;500;600;700')
        ->and($html)->toContain('family=Space+Grotesk:wght@400;500;600;700')
        // No server-side font build step: the CDN link is the only loader.
        ->and($html)->toContain('preconnect" href="https://fonts.gstatic.com"');
});

test('the library ships feed cards in reading order: identity, artwork, price', function () {
    $prompt = uiOverhaulPaidPrompt();
    $creator = $prompt->creator;

    $html = $this->get(route('library.index'))->assertOk()->getContent();

    // The card root: rounded-2xl, no clipping (the composite-box avatar
    // geometry rule) and no rounded-3xl (that radius is for dark blocks).
    preg_match('/<article class="(group relative flex flex-col[^"]*)"[^>]*>(.*?)<\/article>/s', $html, $card);

    expect($card[1] ?? 'no-card')->toContain('rounded-2xl')
        ->and($card[1] ?? 'no-card')->not->toContain('rounded-3xl')
        ->and($card[1] ?? 'no-card')->not->toContain('overflow-hidden');

    $body = $card[2] ?? '';
    expect($body)->not->toBe('');

    $identityAt = strpos($body, 'aria-label="View profile of '.$creator->name.'"');
    $artworkAt = strpos($body, route('prompts.show', $prompt));
    $priceAt = strpos($body, 'Sikka');

    expect($identityAt)->not->toBeFalse('the feed card lost its creator header')
        ->and($artworkAt)->not->toBeFalse()
        ->and($priceAt)->not->toBeFalse('the feed card lost its Sikka price chip');

    // Identity above the artwork; the price chip below it (bottom-right).
    expect($identityAt)->toBeLessThan($artworkAt)
        ->and($priceAt)->toBeGreaterThan($artworkAt);

    // Sikka is the only price on the card — no NPR mirror sneaks back in.
    expect($body)->not->toContain('Rs.');

    // P1 (v1.9.1): the feed is a TRUE single column — one full-width post per
    // row at EVERY breakpoint, on a centered max-w-3xl column with gap-6 air.
    // No multi-column grid survives on this surface.
    expect($html)->toContain('mx-auto grid w-full max-w-3xl grid-cols-1 gap-6')
        ->and($html)->not->toContain('sm:grid-cols-2')
        ->and($html)->not->toContain('xl:grid-cols-3');
});

test('v1.9.1: the library and the homepage feed are single-column newsfeeds', function () {
    cache()->flush();
    uiOverhaulPaidPrompt();

    $library = $this->get(route('library.index'))->assertOk()->getContent();
    $home = $this->get(route('home'))->assertOk()->getContent();

    // Both surfaces carry the same feed container — one column, full-width
    // posts, centered on a max-w-3xl column, gap-6 between posts. (The
    // homepage adds `mt-8` for section rhythm, so the shared contract is the
    // container tail both must carry.)
    expect(substr_count($library, 'mx-auto grid w-full max-w-3xl grid-cols-1 gap-6'))->toBe(1)
        ->and(substr_count($home, 'grid w-full max-w-3xl grid-cols-1 gap-6'))->toBe(1);

    // The featured feed is NOT a carousel any more. Scoped to the section so
    // the chips/stat/category rails elsewhere on the page cannot answer for
    // it: no snap rail, no horizontal scroll, no width-keyed carousel slide
    // and no md:/lg: grid guards.
    preg_match('/Fresh from the library(.*?)<\/section>/s', $home, $featured);
    $feed = $featured[1] ?? '';

    expect($feed)->not->toBe('')
        ->and($feed)->not->toContain('snap-x')
        ->and($feed)->not->toContain('snap-mandatory')
        ->and($feed)->not->toContain('overflow-x-auto')
        ->and($feed)->not->toContain('w-[82%]')
        ->and($feed)->not->toContain('md:grid')
        ->and($feed)->not->toContain('lg:grid-cols-3');

    // Prominent artwork: the feed covers take the wide 16:9 crop (the plain
    // card keeps its 16:10 cover — `large` is opt-in per surface).
    expect($library)->toContain('aspect-[16/9]')
        ->and($feed)->toContain('aspect-[16/9]');

    // Vertical scroll only, closed by a pagination door into the library.
    expect($feed)->toContain('Load more prompts')
        ->and($feed)->toContain(route('library.index'));
});

test('the prompt detail is a two-column shell on desktop and stacks below lg', function () {
    $prompt = uiOverhaulPaidPrompt();
    $buyer = User::factory()->create();

    $html = $this->actingAs($buyer)->get(route('prompts.show', $prompt))->assertOk()->getContent();

    // The shell: no base column count (a phone stacks), two columns from lg.
    expect($html)->toContain('mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]');

    $articleAt = strpos($html, '<article class="min-w-0">');
    $asideAt = strpos($html, '<aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">');

    expect($articleAt)->not->toBeFalse('the left column lost its min-w-0 shell')
        ->and($asideAt)->not->toBeFalse('the buy rail lost its sticky shell');

    // Left column first in the DOM = cover on the left on desktop…
    expect($articleAt)->toBeLessThan($asideAt);

    // …and the buy form rides the right rail, not the long-form column.
    $buyAt = strpos($html, route('checkout.prompts.buy', $prompt));
    expect($buyAt)->not->toBeFalse()
        ->and($buyAt)->toBeGreaterThan($asideAt);
});

test('the dashboard ships the settings-style tab rail and card stats', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // The G3 mobile scroll contract on the rail is untouched.
    expect($html)->toContain('role="navigation" aria-label="Dashboard sections" tabindex="0"')
        ->and($html)->toContain('flex w-full snap-x snap-mandatory gap-2 overflow-x-auto px-4 pb-1')
        ->and($html)->toContain('sm:flex-wrap sm:overflow-visible');

    // Inactive tabs are segmented (no per-pill border), the active tab keeps
    // the ink fill — the WCAG contrast the dashboard has always relied on.
    // Scoped to the rail so another surface's border styling cannot answer
    // for the tabs.
    preg_match('/role="navigation" aria-label="Dashboard sections" tabindex="0">(.*?)<\/div>/s', $html, $rail);
    $tabs = $rail[1] ?? '';

    expect($tabs)->not->toBe('')
        ->and($tabs)->toContain('text-ink/70 hover:bg-paper-deep hover:text-ink')
        ->and($tabs)->toContain('bg-ink text-paper')
        ->and($tabs)->not->toContain('hover:border-ink/30');

    // Card-based stats, two-up on phones.
    expect($html)->toContain('mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4');
});

test('the homepage opens its section rhythm without losing the mobile guards', function () {
    cache()->flush();
    uiOverhaulPaidPrompt();

    $html = $this->get(route('home'))->assertOk()->getContent();

    // S4 spacing: the hero opens up and the paper sections breathe (pt-20).
    // Featured + categories always render here; the image-gallery section
    // only exists when an image prompt is seeded, so the count is a floor.
    expect($html)->toContain('px-4 pb-20 pt-16 text-center sm:px-6 md:pt-24')
        ->and(substr_count($html, 'max-w-7xl px-4 pt-20 sm:px-6'))->toBeGreaterThanOrEqual(2);

    // The G3 contracts the hero pinned are still intact.
    expect($html)->toContain('text-3xl font-bold tracking-tight text-ink sm:mt-6 sm:text-5xl md:text-6xl')
        ->and($html)->toContain('aria-label="Trending searches" tabindex="0"');
});

test('the overhaul leaves the composite box, the five-slot dock and Sikka-only pricing alone', function () {
    cache()->flush();
    $prompt = uiOverhaulPaidPrompt();
    $member = User::factory()->create();

    $html = $this->actingAs($member)->get(route('library.index'))->getContent();

    // Composite box (v1.7.5): a bare wrapper, an inset-0 frame, no clip.
    expect($html)->toContain('relative inline-block isolate')
        ->and($html)->not->toContain('-inset-[');

    // No off-token color helper survived the restyle.
    expect($html)->not->toContain('text-ink0');

    // The dock at phone width: exactly five slots, one saffron fill — the
    // center CTA. `<a ` never counts the CTA, which is also an anchor.
    $home = $this->actingAs($member)->get(route('home'))->getContent();
    preg_match('/<nav aria-label="Mobile primary".*?<\/nav>/s', $home, $dock);

    expect(substr_count($dock[0] ?? '', '<a '))->toBe(5)
        ->and(substr_count($dock[0] ?? '', 'bg-saffron'))->toBe(1)
        ->and($html)->not->toContain('Rs.');

    // Sikka-only buyer surfaces: the detail page prices in credits.
    $detail = $this->actingAs($member)->get(route('prompts.show', $prompt))->getContent();
    expect($detail)->toContain('Sikka')->not->toContain('Rs.');
});
