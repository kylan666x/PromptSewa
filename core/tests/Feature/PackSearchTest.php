<?php

use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S1b (v1.9.0) — packs are findable in the navbar typeahead.
 *
 * The founder's report: bundles never appeared in search at all (the
 * payload had no `packs` key). This battery locks the payload shape, the
 * Sikka-only row price, the served client markup, and the honest
 * exclusions — inactive packs and empty bundles are not products.
 */
function packSearchWorld(): array
{
    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);

    $prompts = collect(range(1, 3))->map(fn (int $i) => Prompt::factory()
        ->for($creator, 'creator')
        ->withVersion()
        ->create([
            'status' => Prompt::STATUS_PUBLISHED,
            'visibility' => Prompt::VISIBILITY_PUBLIC,
            'price_sikka' => 30,
            'title' => 'Pack search prompt '.$i,
        ]));

    $pack = Pack::factory()->create([
        'name' => 'Cold Email Machine',
        'slug' => 'cold-email-machine',
        'tagline' => 'Cold outreach bundle',
        'price_sikka' => 120,
        'is_active' => true,
    ]);
    $pack->prompts()->attach($prompts->pluck('id'));

    return [$pack, $creator, $prompts];
}

test('the typeahead payload carries packs priced in Sikka', function () {
    [$pack] = packSearchWorld();

    $payload = $this->getJson(route('search.preview', ['q' => 'cold email']))->assertOk()->json();

    expect($payload)->toHaveKeys(['prompts', 'packs', 'creators'])
        ->and($payload['packs'])->toHaveCount(1);

    $row = $payload['packs'][0];

    expect($row['name'])->toBe('Cold Email Machine')
        ->and($row['prompts_count'])->toBe(3)
        ->and($row['sikka'])->toBe(120) // credits, per the pack price of record
        ->and($row['url'])->toBe(route('packs.show', $pack))
        ->and($row)->not->toHaveKey('price'); // never an NPR figure
});

test('the served typeahead renders the packs section priced in Sikka only', function () {
    packSearchWorld();

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('x-for="(pack, index) in packs"')
        ->and($html)->toContain("'Sikka '+pack.sikka")       // the Sikka-only row price
        ->and($html)->toContain('prompts_count')             // prompt count on the row
        ->and($html)->not->toContain("'Rs '");               // the retired NPR mirror
});

test('an inactive pack or an empty bundle never surfaces', function () {
    [$pack, , $prompts] = packSearchWorld();

    // Empty: active but no published prompts — not a product.
    Pack::factory()->create([
        'name' => 'Cold Empty',
        'slug' => 'cold-empty',
        'price_sikka' => 10,
        'is_active' => true,
    ]);

    // Inactive, with real contents.
    Pack::factory()->create([
        'name' => 'Cold Inactive',
        'slug' => 'cold-inactive',
        'price_sikka' => 10,
        'is_active' => false,
    ])->prompts()->attach($prompts->pluck('id'));

    $names = collect($this->getJson(route('search.preview', ['q' => 'cold']))->json('packs'))->pluck('name');

    expect($names)->toContain('Cold Email Machine')
        ->and($names)->not->toContain('Cold Empty')
        ->and($names)->not->toContain('Cold Inactive');

    // Sanity: the world's pack is the sellable one.
    expect($pack->fresh()->publishedPrompts()->count())->toBe(3);
});

test('a blank term returns the empty payload shape (packs key included)', function () {
    $this->getJson(route('search.preview', ['q' => '   ']))
        ->assertOk()
        ->assertExactJson(['prompts' => [], 'packs' => [], 'creators' => []]);
});
