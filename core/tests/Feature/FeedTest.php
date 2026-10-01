<?php

use App\Models\Badge;
use App\Models\FeedEvent;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * G6 (v1.7.0) — news feed gates.
 */

function feedEventFor(User $actor, string $type = FeedEvent::TYPE_PROMPT_PUBLISHED, array $meta = []): FeedEvent
{
    return FeedEvent::query()->create([
        'type' => $type,
        'actor_id' => $actor->id,
        'meta' => $meta ?: ['title' => 'A test prompt'],
        'dedupe_key' => uniqid($type),
        'created_at' => now(),
    ]);
}

test('the feed renders for guests and is noindex', function () {
    $actor = User::factory()->create();
    feedEventFor($actor);

    $response = $this->get(route('feed.index'));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('noindex')
        ->and($html)->toContain($actor->name);
});

test('banned actors are excluded at query level', function () {
    $clean = User::factory()->create();
    $banned = User::factory()->create(['banned_at' => now()]);

    feedEventFor($clean);
    feedEventFor($banned);

    $html = $this->get(route('feed.index'))->getContent();

    expect($html)->toContain($clean->name)
        ->and($html)->not->toContain($banned->name);

    // The scope itself excludes at query level, not in the view.
    expect(FeedEvent::query()->publicStream()->count())->toBe(1);
});

test('the feed paginates at 20 per page', function () {
    $actor = User::factory()->create();

    for ($i = 0; $i < 25; $i++) {
        feedEventFor($actor, FeedEvent::TYPE_PROMPT_PUBLISHED, ['title' => "Prompt number {$i}"]);
    }

    $page1 = $this->get(route('feed.index'));
    $page2 = $this->get(route('feed.index', ['page' => 2]));

    expect($page1->getContent())->toContain('Prompt number 24')
        ->and($page2->getContent())->toContain('Prompt number 4');
});

test('the dashboard feed tab shows own events plus global milestones', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();

    feedEventFor($creator, FeedEvent::TYPE_PROMPT_PUBLISHED, ['title' => 'My publish']);
    feedEventFor($other, FeedEvent::TYPE_PROMPT_PUBLISHED, ['title' => 'Their publish']);

    // Global milestone from another actor.
    feedEventFor($other, FeedEvent::TYPE_SALE_MILESTONE, ['title' => 'Milestone prompt', 'sales_count' => 10]);

    $html = $this->actingAs($creator)->get(route('dashboard', ['tab' => 'feed']))->getContent();

    expect($html)->toContain('My publish')            // own event
        ->and($html)->toContain('Milestone prompt')   // global milestone
        ->and($html)->not->toContain('Their publish'); // others' noise excluded
});

test('pack_created events render in the public feed', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_official' => true]);

    $this->actingAs($admin)->post(route('admin.packs.store'), [
        'name' => 'Starter bundle',
        'slug' => 'starter-bundle',
        'description' => 'A pack for new buyers.',
        'price_npr' => 999,
        'is_active' => '1',
    ])->assertRedirect();

    expect(FeedEvent::query()->where('type', FeedEvent::TYPE_PACK_CREATED)->count())->toBe(1)
        ->and($this->get(route('feed.index'))->getContent())->toContain('Starter bundle');
});
