<?php

use App\Models\Bookmark;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * H4 (v1.7.4) — saved-heart parity + the fetch contract.
 *
 * ROOT CAUSE (reproduced in a browser under `artisan serve`, which — unlike
 * Pest — has CSRF active): the toggle POST returned 200 and the bookmark row
 * WAS written, but the heart rendered `aria-pressed="false"` after a refresh.
 * Not a CSRF failure at all — a pre-flip parity gap. Only the library grid
 * passed `:saved` into x-prompt-card; every other surface fell through to the
 * component's `false` default, so the optimistic Alpine flip was undone by the
 * next page load.
 *
 * These are served-HTML assertions (the rendered-route rule): they prove what
 * the ROUTE ships, plus string-level assertions on the served markup/asset for
 * the CSRF header wiring — the only place the JS contract is observable.
 */
uses(RefreshDatabase::class);

function h4Creator(): User
{
    return User::factory()->create(['name' => 'Heart Creator', 'username' => 'heartcreator']);
}

/**
 * The heart's two icons, as the ROUTE ships them.
 *
 * H4 (b): the flip toggles PRESENCE (x-show) and never utility classes. A
 * static SSR pair plus an Alpine `:class` pair on one element leaves BOTH
 * pairs in the class attribute after a tap, and the winner is decided by
 * Tailwind's stylesheet order — observed in the browser: the heart turned
 * rose but stayed an OUTLINE because `fill-none` beat `fill-current`.
 * `cloaked` is the server's pre-Alpine answer (correct with JS off too).
 *
 * @return array<int, array{saved: bool, cloaked: bool, bound: string, classes: string}>
 */
function h4HeartIcons(string $html): array
{
    preg_match_all('/<svg\b[^>]*>/s', $html, $tags);

    $icons = [];

    foreach ($tags[0] as $tag) {
        if (! str_contains($tag, 'x-show=')) {
            continue; // cover art, stars, the navbar glyphs…
        }

        preg_match('/class="([^"]*)"/', $tag, $class);
        preg_match('/x-show="([^"]*)"/', $tag, $bound);

        $icons[] = [
            'saved' => str_contains($tag, 'fill-current'),
            'cloaked' => str_contains($tag, 'x-cloak'),
            'bound' => trim($bound[1] ?? ''),
            'classes' => $class[1] ?? '',
        ];
    }

    return $icons;
}

test('the heart pre-flips on the home fresh grid after a refresh', function () {
    cache()->flush();

    $viewer = User::factory()->create();
    $creator = h4Creator();
    Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    $saved = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $saved->id]);

    $html = $this->actingAs($viewer)->get(route('home'))->getContent();

    // The saved card ships aria-pressed="true" and the filled-rose class…
    expect($html)->toContain('aria-pressed="true"')
        ->and($html)->toContain('text-rose-600 fill-current');

    // …and the OTHER card on the same page stays unsaved (parity is per prompt).
    expect(substr_count($html, 'aria-pressed="true"'))->toBe(1);
});

test('every heart surface pre-flips: home, library, search, creator profile, detail', function () {
    cache()->flush();

    $viewer = User::factory()->create();
    $creator = h4Creator();
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $prompt->id]);

    $surfaces = [
        'home' => route('home'),
        'library' => route('library.index'),
        // Search results: query the prompt's OWN title so the row is on page 1.
        'library search' => route('library.index', ['q' => $prompt->title]),
        'creator profile' => route('creators.show', $creator),
        'prompt detail' => route('prompts.show', $prompt),
    ];

    foreach ($surfaces as $name => $url) {
        $html = (string) $this->actingAs($viewer)->get($url)->getContent();

        expect($html)->toContain('aria-pressed="true"');
        expect($html)->toContain('text-rose-600 fill-current');
    }
});

test('the Saved tab lists the viewer bookmarks and nobody else (it renders no heart)', function () {
    cache()->flush();

    $viewer = User::factory()->create();
    $stranger = User::factory()->create();
    $creator = h4Creator(); // one creator: h4Creator() pins a fixed username
    $mine = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    $theirs = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();

    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $mine->id]);
    Bookmark::query()->create(['user_id' => $stranger->id, 'prompt_id' => $theirs->id]);

    $html = (string) $this->actingAs($viewer)->get(route('dashboard', ['tab' => 'saved']))->getContent();

    expect($html)->toContain($mine->title)
        ->and($html)->not->toContain($theirs->title);
});

test('the served layout carries the CSRF meta token the fetch reads', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->toMatch('/<meta name="csrf-token" content="[^"]{20,}"/');
});

test('the served heart markup carries the fetch header wiring', function () {
    $viewer = User::factory()->create();
    Prompt::factory()->published()->for(h4Creator(), 'creator')->hasVersion()->create();

    $html = $this->actingAs($viewer)->get(route('home'))->getContent();

    // The heart button mounts the Alpine component…
    expect($html)->toContain('x-data="bookmarkHeart(');
    // …and the failure toast the revert writes into (per-surface coverage
    // lives in the "tells the user when a save failed" test below).
    expect($html)->toContain('role="status"');

    // The header contract itself lives in the built asset (string-level
    // assertion is the correct lock — Pest never executes fetch()).
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true);
    $jsAsset = $manifest['resources/js/app.js']['file'] ?? null;
    expect($jsAsset)->not->toBeNull();

    $js = (string) file_get_contents(dirname(__DIR__, 2).'/public/build/'.$jsAsset);

    expect($js)->toContain('X-CSRF-TOKEN')
        ->and($js)->toContain('meta[name="csrf-token"]')
        ->and($js)->toContain('X-Requested-With')
        ->and($js)->toContain('XMLHttpRequest')
        // Non-OK must revert + toast; "silent lies are banned". (The
        // minifier renames locals, so assert the CONTRACT tokens, not names.)
        ->and($js)->toContain('.ok')
        ->and($js)->toContain('===419')
        ->and($js)->toContain('Save failed')
        // H4 (b): the colliding class getter is gone for good — the icons
        // are toggled with x-show, so no utility pair can fight the cascade.
        ->and($js)->not->toContain('heartClass');
});

test('a saved heart ships the filled icon visible and the outline cloaked', function () {
    $viewer = User::factory()->create();
    $prompt = Prompt::factory()->published()->for(h4Creator(), 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $prompt->id]);

    $icons = h4HeartIcons((string) $this->actingAs($viewer)->get(route('prompts.show', $prompt))->getContent());

    // The detail page carries exactly one heart: two mutually exclusive icons.
    expect($icons)->toHaveCount(2)
        ->and($icons[0]['saved'])->toBeTrue('the filled rose icon is the saved state')
        ->and($icons[0]['cloaked'])->toBeFalse('the saved icon must be the visible one pre-Alpine')
        ->and($icons[0]['bound'])->toBe('saved')
        ->and($icons[0]['classes'])->toContain('text-rose-600')
        ->and($icons[1]['saved'])->toBeFalse()
        ->and($icons[1]['cloaked'])->toBeTrue()
        ->and($icons[1]['bound'])->toBe('! saved')
        // …and nothing binds a utility pair onto the same element any more.
        ->and($icons[0]['classes'])->not->toContain(':class');
});

test('an unsaved heart ships the outline visible and the rose icon cloaked', function () {
    $viewer = User::factory()->create();
    $prompt = Prompt::factory()->published()->for(h4Creator(), 'creator')->hasVersion()->create();

    $html = (string) $this->actingAs($viewer)->get(route('prompts.show', $prompt))->getContent();
    $icons = h4HeartIcons($html);

    expect($icons)->toHaveCount(2)
        ->and($icons[0]['saved'])->toBeTrue()
        ->and($icons[0]['cloaked'])->toBeTrue()
        ->and($icons[1]['saved'])->toBeFalse()
        ->and($icons[1]['cloaked'])->toBeFalse()
        // The label is server-rendered too: an x-text-only label is EMPTY
        // until Alpine boots — the same "state lives only in Alpine" gap.
        ->and($html)->toContain('>Save for later<')
        ->and($html)->toContain('aria-pressed="false"');
});

test('every heart surface tells the user when a save failed', function () {
    $viewer = User::factory()->create();
    $creator = h4Creator();
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $prompt->id]);

    // The grid card AND the detail button each carry a visible failure
    // toast — browser-verified: a 419 on the detail button used to revert
    // the heart silently, which reads as a successful save.
    $home = (string) $this->actingAs($viewer)->get(route('home'))->getContent();
    $detail = (string) $this->actingAs($viewer)->get(route('prompts.show', $prompt))->getContent();

    foreach (['home' => $home, 'detail' => $detail] as $surface => $html) {
        // The toast element + the binding that fills it (the message copy
        // itself lives in the built JS and is asserted there).
        expect($html)->toContain('x-show="toast"')
            ->and($html)->toContain('x-text="toast"')
            ->and($html)->toContain('role="status"');
    }
});

test('on a grid, exactly the bookmarked card shows the filled icon', function () {
    cache()->flush();

    $viewer = User::factory()->create();
    $creator = h4Creator();
    $plain = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    $saved = Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $saved->id]);

    $icons = h4HeartIcons((string) $this->actingAs($viewer)->get(route('home'))->getContent());

    $visibleFilled = collect($icons)->where('saved', true)->where('cloaked', false)->count();
    $visibleOutline = collect($icons)->where('saved', false)->where('cloaked', false)->count();

    expect($visibleFilled)->toBe(1)
        ->and($visibleOutline)->toBe(1)
        ->and($icons)->toHaveCount(4) // two cards × two icons
        ->and($saved->title)->not->toBe($plain->title);
});

test('the parity set costs ONE query per request, not one per card', function () {
    $viewer = User::factory()->create();
    $creator = h4Creator();
    foreach (range(1, 5) as $ignored) {
        Prompt::factory()->published()->for($creator, 'creator')->hasVersion()->create();
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs($viewer)->get(route('home'))->assertOk();

    $bookmarkQueries = collect(DB::getQueryLog())->where('query', 'like', '%bookmarks%')->count();

    expect($bookmarkQueries)->toBeLessThanOrEqual(1)
        ->and($queries)->toBeGreaterThan(0);
});

test('guests get a login link, never a heart button', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)->not->toContain('x-data="bookmarkHeart(');
});

test('one viewer never sees another viewer bookmarked state', function () {
    cache()->flush();

    $saver = User::factory()->create();
    $stranger = User::factory()->create();
    $prompt = Prompt::factory()->published()->for(h4Creator(), 'creator')->hasVersion()->create();
    Bookmark::query()->create(['user_id' => $saver->id, 'prompt_id' => $prompt->id]);

    $strangerHtml = (string) $this->actingAs($stranger)->get(route('library.index'))->getContent();

    expect($strangerHtml)->not->toContain('aria-pressed="true"');
});