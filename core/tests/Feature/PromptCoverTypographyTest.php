<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coverCreator(): User
{
    return User::factory()->create(['role' => User::ROLE_CREATOR]);
}

/** Decode the data-URI SVG banner out of rendered card HTML. */
function bannerSvg(string $html): string
{
    expect(preg_match('/data:image\/svg\+xml;charset=utf-8,([^"]+)/', $html, $m))->toBe(1, 'no generated banner found in HTML');

    return rawurldecode($m[1]);
}

test('short title renders untruncated and horizontally centered', function () {
    $prompt = Prompt::factory()->published()->for(coverCreator(), 'creator')->create(['title' => 'Cold Email']);

    $svg = bannerSvg($this->get(route('prompts.show', $prompt))->getContent());

    expect($svg)->toContain('text-anchor="middle"')
        ->toContain('dominant-baseline="middle"')
        ->toContain('>Cold Email</tspan>')
        ->not->toContain('…');
});

test('long title truncates with an ellipsis at a word boundary', function () {
    $prompt = Prompt::factory()->published()->for(coverCreator(), 'creator')->create([
        'title' => 'Engineering & Product Operations Weekly Digest Framework For Growing Teams And Stakeholders',
    ]);

    $svg = bannerSvg($this->get(route('prompts.show', $prompt))->getContent());

    expect($svg)->toContain('…')
        ->not->toMatch('/[&—,]\s*…/u'); // no dangling ampersand/dash before the ellipsis
});

test('banner text block never overflows the viewBox bottom', function () {
    $titles = [
        'Hi',
        'Cold Email Sequencer That Actually Gets Replies',
        'Engineering & Product Operations Weekly Digest Framework For Growing Teams And Stakeholders Everywhere',
        str_repeat('Supercalifragilistic ', 6),
    ];

    foreach ($titles as $title) {
        $prompt = Prompt::factory()->published()->for(coverCreator(), 'creator')->create(['title' => $title]);
        $svg = bannerSvg($this->get(route('prompts.show', $prompt))->getContent());

        preg_match_all('/<text[^>]*y="([\d.]+)"[^>]*font-size="(\d+)"(.*)<\/text>/s', $svg, $m, PREG_SET_ORDER);
        expect($m)->not->toBeEmpty("no text node for: {$title}");

        foreach ($m as [$all, $y, $fs, $inner]) {
            preg_match_all('/dy="(\d+)"/', $inner, $dys) ?: [];
            $lastOffset = array_sum($dys[1] ?? []);
            $bottom = (float) $y + $lastOffset + ((float) $fs * 0.35); // baseline + descent
            expect($bottom)->toBeLessThanOrEqual(250.0, "title overflows viewBox bottom: {$title}");
            expect((float) $y - (float) $fs)->toBeGreaterThanOrEqual(-5.0, "title overflows viewBox top: {$title}");
        }
    }
});

test('banner keeps the deterministic palette', function () {
    $creator = coverCreator();
    $a = Prompt::factory()->published()->for($creator, 'creator')->create(['title' => 'Alpha One']);
    $b = Prompt::factory()->published()->for($creator, 'creator')->create(['title' => 'Beta Two']);

    $svgA = bannerSvg($this->get(route('prompts.show', $a))->getContent());
    $svgB = bannerSvg($this->get(route('prompts.show', $b))->getContent());

    // Same id % palette mapping as before: same prompt id, same colors —
    // deterministic across renders.
    expect($svgA)->toContain('linearGradient')
        ->and($svgB)->toContain('linearGradient')
        ->and(bannerSvg($this->get(route('prompts.show', $a))->getContent()))->toBe($svgA);
});
