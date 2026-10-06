<?php

use App\Models\Bookmark;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * F3 (v1.9.2) — the TRUE Facebook/Instagram feed replica.
 *
 * The founder's report: a vague "single column" is not enough — the DOM has to
 * match the social mental model. Two shapes ship from one component:
 *
 *   * TEXT / AGENTIC / SKILL listings  -> the Facebook text post: identity
 *     header, the copy as the post body (`whitespace-pre-wrap`,
 *     `leading-relaxed`), then the action bar. NO artwork.
 *   * IMAGE / VIDEO listings           -> the Instagram post: identity header,
 *     edge-to-edge artwork (`aspect-square md:aspect-[4/5]`), caption, action
 *     bar.
 *
 * Both end in the same action bar (Like · Comment | Share · Save) and both
 * keep the Sikka price chip — the invariant that must survive the redesign.
 *
 * The feed section itself must carry no grid/carousel markers: one column,
 * vertical scroll only, at every breakpoint.
 */
uses(RefreshDatabase::class);

/** The served markup of the first feed card (`<article>…</article>`). */
function firstFeedCard(string $html): string
{
    preg_match('/<article class="group relative flex flex-col.*?<\/article>/s', $html, $match);

    return $match[0] ?? '';
}

/** A published listing owned by $creator, of the given prompt type. */
function feedPost(User $creator, string $type, ?string $description = null): Prompt
{
    return Prompt::factory()
        ->for($creator, 'creator')
        ->hasVersion()
        ->priced(19_900)
        ->published()
        ->create([
            'type' => $type,
            'visibility' => Prompt::VISIBILITY_PUBLIC,
            'description' => $description ?? 'A short, honest description of what this prompt does.',
        ]);
}

test('a text prompt renders as a Facebook-style post with the full action bar', function () {
    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_TEXT);

    $html = $this->get(route('library.index'))->assertOk()->getContent();
    $card = firstFeedCard($html);

    expect($card)->not->toBe('');

    // Container: the social post shell.
    expect($card)->toContain('rounded-xl border border-ink/5 bg-paper shadow-sm')
        // Header row (identity) — padding differs per shape: p-4 text / p-3 visual.
        ->and($card)->toContain('flex items-center gap-3 p-4')
        // Body: the copy IS the post.
        ->and($card)->toContain('class="px-4 pb-3"')
        ->and($card)->toContain('whitespace-pre-wrap text-base leading-relaxed text-ink')
        // Action bar. (The labels sit on their own lines in the markup, so the
        // assertions are whitespace-tolerant rather than chasing indentation.)
        ->and($card)->toContain('border-t border-ink/5 px-4 py-2')
        ->and($card)->toMatch('/>\s*Like\s*</')
        ->and($card)->toMatch('/>\s*Comment\s*</')
        ->and($card)->toMatch('/>\s*Share\s*</')
        ->and($card)->toMatch('/>\s*Save\s*</');

    // A text post carries no artwork at all (the Facebook shape).
    expect($card)->not->toContain('aspect-square')
        ->and($card)->not->toContain('aspect-[4/5]');
});

test('an image prompt renders as an Instagram-style post with edge-to-edge art', function () {
    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_IMAGE);

    $html = $this->get(route('library.index'))->assertOk()->getContent();
    $card = firstFeedCard($html);

    expect($card)->not->toBe('')
        // Container + tighter header.
        ->and($card)->toContain('rounded-xl border border-ink/5 bg-paper shadow-sm')
        ->and($card)->toContain('flex items-center gap-3 p-3')
        // Artwork: square on phones, 4:5 from md, with the ink ground.
        ->and($card)->toContain('aspect-square bg-ink/5 md:aspect-[4/5]')
        ->and($card)->toContain('object-cover')
        // Caption block + the same action bar.
        ->and($card)->toContain('class="p-4"')
        ->and($card)->toContain('border-t border-ink/5 px-4 py-2')
        ->and($card)->toMatch('/>\s*Like\s*</')
        ->and($card)->toMatch('/>\s*Share\s*</')
        ->and($card)->toMatch('/>\s*Save\s*</');
});

test('the action bar is present on every card on every feed surface', function () {
    cache()->flush();

    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_TEXT);
    feedPost($creator, Prompt::TYPE_IMAGE);

    foreach (['library' => route('library.index'), 'home' => route('home'), 'profile' => route('creators.show', $creator)] as $surface => $url) {
        $html = $this->get($url)->assertOk()->getContent();

        // Count only the F3 social shell — the image-gallery tiles share the
        // `<article>` element but are a different (S4 tile) card shape.
        $cards = substr_count($html, 'rounded-xl border border-ink/5 bg-paper shadow-sm');

        expect($cards)->toBeGreaterThan(0, "no feed cards on {$surface}")
            ->and(substr_count($html, 'border-t border-ink/5 px-4 py-2'))->toBe($cards, "action bar count != card count on {$surface}")
            ->and(preg_match_all('/>\s*Like\s*</', $html))->toBe($cards)
            ->and(preg_match_all('/>\s*Comment\s*</', $html))->toBe($cards);
    }
});

test('the feed carries no grid or carousel markers on either feed surface', function () {
    cache()->flush();

    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_TEXT);

    // Library: single column, no multi-column grid.
    $library = $this->get(route('library.index'))->assertOk()->getContent();
    expect($library)->toContain('mx-auto grid w-full max-w-3xl grid-cols-1 gap-6')
        ->and($library)->not->toContain('sm:grid-cols-2')
        ->and($library)->not->toContain('xl:grid-cols-3');

    // Homepage featured feed: same column, no carousel.
    $home = $this->get(route('home'))->assertOk()->getContent();
    preg_match('/Fresh from the library(.*?)<\/section>/s', $home, $featured);
    $feed = $featured[1] ?? '';

    expect($feed)->not->toBe('')
        ->and($feed)->not->toContain('snap-x')
        ->and($feed)->not->toContain('overflow-x-auto')
        ->and($feed)->not->toContain('w-[82%]')
        ->and($feed)->not->toContain('grid-cols-2')
        ->and($feed)->not->toContain('grid-cols-3')
        ->and($feed)->toContain('grid-cols-1');
});

test('Like and Comment are honest stubs — no dead buttons, no href="#"', function () {
    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_TEXT);

    $html = $this->get(route('library.index'))->assertOk()->getContent();
    $card = firstFeedCard($html);

    // They name what they are instead of pretending to work…
    expect($card)->toContain('title="Likes are coming soon"')
        ->and($card)->toContain('title="Comments are coming soon"')
        // …and they are spans, not focusable controls that do nothing.
        ->and($card)->not->toContain('href="#"');
});

test('Share is a real control wired to the Web Share API with a clipboard fallback', function () {
    $creator = User::factory()->create();
    $prompt = feedPost($creator, Prompt::TYPE_TEXT);

    $html = $this->get(route('library.index'))->assertOk()->getContent();
    $card = firstFeedCard($html);

    expect($card)->toContain('x-data="sharePost(')
        ->and($card)->toContain(route('prompts.show', $prompt))
        ->and($card)->toContain('aria-label="Share '.$prompt->title.'"');

    // The contract lives in the built asset (Pest never runs navigator).
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/public/build/manifest.json'), true);
    $asset = $manifest['resources/js/app.js']['file'] ?? null;
    expect($asset)->not->toBeNull('built JS asset missing — run npm run build');

    $js = (string) file_get_contents(dirname(__DIR__, 2).'/public/build/'.$asset);

    expect($js)->toContain('navigator.share')
        ->and($js)->toContain('clipboard.writeText')
        // A dismissed share sheet is not a failure (AbortError branch).
        ->and($js)->toContain('AbortError')
        // Honest toast when neither API exists.
        ->and($js)->toContain('Copy the link from your address bar to share');
});

test('Save keeps the real bookmark wiring and the bookmarked card shows the filled heart', function () {
    $viewer = User::factory()->create();
    $creator = User::factory()->create();
    $prompt = feedPost($creator, Prompt::TYPE_TEXT);

    Bookmark::query()->create(['user_id' => $viewer->id, 'prompt_id' => $prompt->id]);

    $card = firstFeedCard($this->actingAs($viewer)->get(route('library.index'))->assertOk()->getContent());

    expect($card)->toContain('x-data="bookmarkHeart(')
        ->and($card)->toContain('aria-pressed="true"')
        ->and($card)->toContain('text-rose-600 fill-current')
        ->and($card)->toContain('Save');

    // A guest gets a sign-in door instead of a heart button.
    $this->app->make('auth')->guard('web')->forgetUser();
    $guest = firstFeedCard($this->get(route('library.index'))->assertOk()->getContent());

    expect($guest)->not->toContain('bookmarkHeart(')
        ->and($guest)->toContain(route('login'));
});

test('long copy is split at 500 characters behind a See more control', function () {
    $creator = User::factory()->create();
    $long = str_repeat('This prompt walks through the whole workflow. ', 20); // ~900 chars

    expect(mb_strlen($long))->toBeGreaterThan(500);

    // Two listings on one feed page: one long, one short.
    feedPost($creator, Prompt::TYPE_TEXT, $long);
    feedPost($creator, Prompt::TYPE_TEXT, 'Short and sweet.');

    $html = $this->get(route('library.index'))->assertOk()->getContent();

    // The visible half is the first 500 characters — and the remainder is in
    // the DOM too, so the toggle reveals real text rather than re-fetching.
    expect($html)->toContain(rtrim(mb_substr($long, 0, 500)))
        ->and($html)->toContain(trim(mb_substr($long, 500)))
        // …and only the long post gets the control: the short one is not
        // decorated with a toggle it does not need.
        ->and(substr_count($html, 'See more'))->toBe(1)
        ->and($html)->toContain('Short and sweet.');
});

test('the Sikka price chip survives the replica — and NPR never appears', function () {
    $creator = User::factory()->create();
    feedPost($creator, Prompt::TYPE_IMAGE);

    $html = $this->get(route('library.index'))->assertOk()->getContent();
    $card = firstFeedCard($html);

    expect($card)->toContain('Sikka')
        ->and($card)->not->toContain('Rs.')
        ->and($html)->not->toContain('Rs.');
});
