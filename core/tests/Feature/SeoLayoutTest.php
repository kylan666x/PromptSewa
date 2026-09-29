<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Pull only the <head> block out of a served page. */
function headOf(string $html): string
{
    preg_match('/<head>(.*?)<\/head>/s', $html, $m);

    return $m[1] ?? '';
}

test('the homepage serves a dynamic title and og:title inside <head>', function () {
    $head = headOf($this->get(route('home'))->getContent());

    preg_match_all('/<title>(.*?)<\/title>/s', $head, $titles);

    expect(count($titles[1]))->toBe(1, 'exactly one <title> in <head>')
        ->and($titles[1][0])->toBe('PromptSewa')
        ->and($head)->toContain('property="og:title"')
        ->and($head)->toContain('name="description"')
        ->and($head)->toContain('rel="canonical"');
});

test('the prompt detail page title leads with the prompt subject', function () {
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create([
        'title' => 'Head Test Cinematic Probe',
    ]);

    $head = headOf($this->get(route('prompts.show', $prompt))->getContent());
    preg_match_all('/<title>(.*?)<\/title>/s', $head, $titles);

    expect(count($titles[1]))->toBe(1)
        ->and($titles[1][0])->toStartWith('Head Test Cinematic Probe')
        ->and($head)->toContain('property="og:title"');
});

test('the creator profile serves a ProfilePage title and og tags', function () {
    $creator = User::factory()->create(['username' => 'seoprofile', 'name' => 'Seo Profile']);
    Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $head = headOf($this->get(route('creators.show', $creator))->getContent());
    preg_match_all('/<title>(.*?)<\/title>/s', $head, $titles);

    expect(count($titles[1]))->toBe(1)
        ->and($titles[1][0])->toStartWith('Seo Profile')
        ->and($head)->toContain('property="og:title"');
});

test('no hardcoded brand-only title tag survives in any layout', function () {
    $layoutFiles = glob(__DIR__.'/../../resources/views/components/*layout*.blade.php');

    expect($layoutFiles)->not->toBeEmpty();

    foreach ($layoutFiles as $file) {
        $contents = file_get_contents($file);
        // Count real <title> elements (skip Blade comments mentioning the tag).
        $clean = preg_replace('/\{\{--.*?--\}\}/s', '', $contents);
        expect(substr_count($clean, '<title>'))->toBe(0, "{$file} must not carry a hardcoded <title> — x-seo is the single source of truth.")
            ->and($clean)->not->toContain('name="description"');
    }
});

test('seo tags never render in the body', function () {
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $html = $this->get(route('prompts.show', $prompt))->getContent();
    preg_match('/<body.*<\/body>/s', $html, $body);

    expect($body[0] ?? '')->not->toContain('<title>')
        ->and($body[0] ?? '')->not->toContain('property="og:title"');
});

// ---------------------------------------------------------------- F2

test('the typeahead dropdown template renders official and verified badge markup', function () {
    $template = file_get_contents(__DIR__.'/../../resources/views/components/navbar.blade.php');

    // The dropdown carries both badge variants, gated on the JSON badge field.
    expect($template)->toContain("creator.badge === 'official'")
        ->and($template)->toContain("creator.badge === 'verified'")
        // Official badge = the blue circle mark; verified = the saffron seal.
        ->and($template)->toContain('#1d9bf0')
        ->and($template)->toContain('#EAB308');
});

test('official badge SVG appears in the served page for the official creator card', function () {
    // The official account's byline surfaces the blue circle on a prompt page.
    $official = User::factory()->create([
        'role' => User::ROLE_ADMIN,
        'is_verified' => true,
        'is_official' => true,
    ]);
    $prompt = Prompt::factory()->for($official, 'creator')->hasVersion()->create();

    $html = $this->get(route('prompts.show', $prompt))->getContent();

    expect($html)->toContain('#1d9bf0');
});
