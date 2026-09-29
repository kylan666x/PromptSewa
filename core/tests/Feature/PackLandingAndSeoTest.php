<?php

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------- T8

test('pack landing renders tagline, hero copy and Product JSON-LD', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $pack = Pack::factory()->create([
        'tagline' => 'Every launch prompt you will ever need',
        'hero_copy' => "Built for founders shipping weekly.\nTen battle-tested prompts, one license.",
    ]);
    $prompt = Prompt::factory()->for($admin, 'creator')->hasVersion()->create();
    $pack->prompts()->sync([$prompt->id]);

    $html = $this->get(route('packs.show', $pack))
        ->assertOk()
        ->assertSee('Every launch prompt you will ever need')
        ->assertSee('Built for founders shipping weekly.', false)
        ->getContent();

    expect($html)->toContain('application/ld+json')
        ->and($html)->toContain('"@type":"Product"')
        ->and($html)->toContain('InStock');
});

test('admin pack form persists tagline and hero copy', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.packs.store'), [
            'name' => 'Launch Season Pack',
            'tagline' => 'Ship it faster',
            'hero_copy' => 'The full narrative for the landing page.',
            'price_npr' => 999,
            'is_active' => '1',
            'position' => 0,
        ])
        ->assertRedirect(route('admin.packs.index'));

    $pack = Pack::query()->where('name', 'Launch Season Pack')->first();
    expect($pack->tagline)->toBe('Ship it faster')
        ->and($pack->hero_copy)->toBe('The full narrative for the landing page.');
});

// ---------------------------------------------------------------- T9

test('public pages carry canonical, OG tags and JSON-LD', function () {
    $creator = User::factory()->create(['username' => 'seopro']);
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create([
        'title' => 'Search Optimized Probe Listing',
        'type' => Prompt::TYPE_TEXT,
    ]);

    $html = $this->get(route('prompts.show', $prompt))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('rel="canonical"')
        ->and($html)->toContain('property="og:title"')
        ->and($html)->toContain('name="twitter:card"')
        ->and($html)->toContain('application/ld+json');

    $creatorHtml = $this->get(route('creators.show', $creator))->getContent();
    expect($creatorHtml)->toContain('"ProfilePage"');
});

test('auth pages are noindex and versions canonical points at the prompt', function () {
    $html = $this->get(route('login'))->getContent();
    expect($html)->toContain('noindex');

    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();

    $versionsHtml = $this->get(route('prompts.versions', $prompt))->getContent();
    expect($versionsHtml)->toContain('rel="canonical" href="'.route('prompts.show', $prompt).'"');
});

test('sitemap and robots respond with public surfaces only', function () {
    $creator = User::factory()->create(['username' => 'mapped']);
    $prompt = Prompt::factory()->for($creator, 'creator')->hasVersion()->create();
    Prompt::factory()->for($creator, 'creator')->pending()->create(); // never listed

    $sitemap = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($sitemap)->toContain(route('prompts.show', $prompt))
        ->not->toContain('pending');

    $robots = $this->get(route('robots'))
        ->assertOk()
        ->getContent();

    expect($robots)->toContain('Sitemap: '.route('sitemap'))
        ->and($robots)->toContain('Disallow: /admin');
});
