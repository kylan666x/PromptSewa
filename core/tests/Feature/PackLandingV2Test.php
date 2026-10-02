<?php

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * G2 (v1.7.4) — pack landing v2.
 *
 * The voice rule is the test: the inspiration contributed LAYOUT (dark hero
 * band, sticky buy anchor, dark contents grid), never copy. These assertions
 * exist so nobody can add a fabricated count, testimonial or urgency banner
 * later — every figure on the page must be a real aggregate, and the honest
 * empty states ("No ratings yet", "no published prompts yet") must survive.
 */
uses(RefreshDatabase::class);

function v174Pack(array $attrs = []): Pack
{
    return Pack::query()->create(array_merge([
        'name' => 'Launch Kit',
        'slug' => 'launch-kit',
        'description' => 'Everything for a launch week.',
        'tagline' => 'Ship the launch in seven days',
        'price_paisa' => 450_000,
        'currency' => 'NPR',
        'is_active' => true,
    ], $attrs));
}

test('the ink hero band carries the pack name, tagline and real stat chips', function () {
    $pack = v174Pack();
    $creator = User::factory()->create();
    Prompt::factory()->published()->for($creator, 'creator')->create();
    $pack->prompts()->attach(Prompt::query()->published()->first()->id);

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('rounded-3xl bg-ink px-6 py-10')
        ->and($html)->toContain('Ship the launch in seven days')
        ->and($html)->toContain('Launch Kit')
        // Real count: exactly one published prompt is attached.
        ->and($html)->toContain('1 prompt');
});

test('an unrated pack says No ratings yet and never shows a made-up average', function () {
    $pack = v174Pack();

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('No ratings yet')
        ->and($html)->not->toContain('★ 5.0')
        ->and($html)->not->toContain('★ 4.');
});

test('the star figure is the real average over the pack ratings', function () {
    $pack = v174Pack();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create();
    $pack->prompts()->attach($prompt->id);

    Rating::query()->create(['user_id' => User::factory()->create()->id, 'prompt_id' => $prompt->id, 'score' => 5]);
    Rating::query()->create(['user_id' => User::factory()->create()->id, 'prompt_id' => $prompt->id, 'score' => 4]);

    $html = $this->get(route('packs.show', $pack))->getContent();

    // AVG(5, 4) = 4.5 with a real count — not a rounded-up marketing number.
    expect($html)->toContain('★ 4.5')
        ->and($html)->toContain('(2 ratings)');
});

test('savings appear only when the contents really cost more', function () {
    $pack = v174Pack(['price_paisa' => 150_000]); // Rs. 1,500
    $creator = User::factory()->create();

    $expensive = Prompt::factory()->published()->for($creator, 'creator')->create(['price_cents' => 300_000]);
    $pack->prompts()->attach($expensive->id);

    $html = $this->get(route('packs.show', $pack))->getContent();
    expect($html)->toContain('Save Rs. 1,500 vs buying individually');

    // Pack dearer than its contents: NO savings claim is printed.
    $rich = v174Pack(['name' => 'Rich Pack', 'slug' => 'rich-pack', 'price_paisa' => 900_000]);
    $free = Prompt::factory()->published()->for($creator, 'creator')->create(['price_cents' => 0]);
    $rich->prompts()->attach($free->id);

    $html2 = $this->get(route('packs.show', $rich))->getContent();
    expect($html2)->not->toContain('Save Rs.')
        ->and($html2)->not->toContain('line-through');
});

test('contents render as a dark grid with type icon, category and price', function () {
    $pack = v174Pack();
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->published()->for($creator, 'creator')->create(['title' => 'Launch email', 'price_cents' => 0]);
    $pack->prompts()->attach($prompt->id);

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('Launch email')
        ->and($html)->toContain('bg-ink p-4')          // dark contents card
        ->and($html)->toContain('text-emerald-300')     // Free price chip
        ->and($html)->toContain('Free');
});

test('the licence checklist states facts, not superlatives', function () {
    $pack = v174Pack();

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('What your purchase includes')
        ->and($html)->toContain('newest published version')
        ->and($html)->toContain('not permitted')   // no-resale fact
        // Voice rule: no invented social proof on the page.
        ->and($html)->not->toContain('trusted by')
        ->and($html)->not->toContain('customers love')
        ->and($html)->not->toContain('testimonial');
});

test('the FAQ answers real policy questions', function () {
    $pack = v174Pack();

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('Questions before you buy')
        ->and($html)->toContain('eSewa')
        ->and($html)->toContain('payment proof')
        ->and($html)->toContain('not refundable');
});

test('related packs list real rows and stay off the pack itself', function () {
    $pack = v174Pack();
    $other = v174Pack(['name' => 'Second Kit', 'slug' => 'second-kit']);
    $creator = User::factory()->create();
    $pack->prompts()->attach(Prompt::factory()->published()->for($creator, 'creator')->create()->id);
    $other->prompts()->attach(Prompt::factory()->published()->for($creator, 'creator')->create()->id);

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('You might also like')
        ->and($html)->toContain('Second Kit');

    // The current pack must not recommend itself.
    preg_match('/You might also like.*?<\/section>/s', $html, $section);
    expect($section[0] ?? '')->not->toContain('Launch Kit');
});

test('an empty pack degrades honestly and keeps the buy flow', function () {
    $pack = v174Pack();

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('no published prompts yet')
        ->and($html)->toContain('0 prompts');
});

test('JSON-LD and the x-seo head survive the redesign', function () {
    $pack = v174Pack();

    $html = $this->get(route('packs.show', $pack))->getContent();

    expect($html)->toContain('"@type":"Product"')
        ->and($html)->toContain('"@type":"Offer"')
        ->and($html)->toContain('"price":"4500.00"');

    preg_match('/<head>(.*?)<\/head>/s', $html, $head);
    expect($head[1] ?? '')->toContain('<title>Launch Kit')
        ->and($head[1] ?? '')->toContain('rel="canonical"');
});