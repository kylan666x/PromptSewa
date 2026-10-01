<?php

use App\Models\Badge;
use App\Models\Prompt;
use App\Models\Rating;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * G6 (v1.7.0) — gamification gates. Award idempotency is the UNIQUE(user,
 * badge) constraint; XP is integer-only; levels are a pure function.
 */

function autoBadge(string $criterion): Badge
{
    return Badge::query()->create([
        'name' => ucfirst(str_replace('_', ' ', $criterion)),
        'slug' => $criterion,
        'criterion' => $criterion,
        'is_active' => true,
    ]);
}

test('badge award is idempotent under double-fire', function () {
    $user = User::factory()->create();
    $badge = autoBadge('first_publish');

    $service = app(GamificationService::class);

    $first = $service->awardBadge($user, $badge);
    $second = $service->awardBadge($user, $badge); // double fire

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(UserBadge::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('manual award is audited with awarder and reason', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $creator = User::factory()->create();
    $badge = autoBadge('verified');

    $this->actingAs($admin)->post(route('admin.badges.award'), [
        'badge_id' => $badge->id,
        'user_id' => $creator->id,
        'reason' => 'Community spotlight pick',
    ])->assertRedirect()->assertSessionHas('success');

    $row = UserBadge::query()->where('user_id', $creator->id)->first();

    expect($row->awarded_by)->toBe($admin->id)
        ->and($row->reason)->toBe('Community spotlight pick');

    // Second award is refused with an error, no duplicate row.
    $this->actingAs($admin)->post(route('admin.badges.award'), [
        'badge_id' => $badge->id,
        'user_id' => $creator->id,
        'reason' => 'Trying again',
    ])->assertRedirect()->assertSessionHasErrors('award');

    expect(UserBadge::query()->count())->toBe(1);
});

test('xp thresholds and level chip are pure functions of xp', function () {
    expect(GamificationService::levelForXp(0))->toBe(1)
        ->and(GamificationService::levelForXp(499))->toBe(1)
        ->and(GamificationService::levelForXp(500))->toBe(2)
        ->and(GamificationService::levelForXp(1499))->toBe(2)
        ->and(GamificationService::levelForXp(1500))->toBe(3)
        ->and(GamificationService::levelForXp(22500))->toBe(10)
        ->and(GamificationService::levelForXp(999999))->toBe(10);
});

test('publishing pays xp and awards first_publish with a feed event', function () {
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->draft()->create();

    autoBadge('first_publish');

    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    expect((int) $creator->refresh()->xp)->toBe(50 + 200) // publish + badge_earned
        ->and(UserBadge::query()->where('user_id', $creator->id)->count())->toBe(1)
        ->and(\App\Models\FeedEvent::query()->where('type', 'prompt_published')->count())->toBe(1);

    // Re-saving the published prompt must not double-emit. (Total is 2:
    // prompt_published + badge_earned — the badge award also emits.)
    $prompt->save();

    expect(\App\Models\FeedEvent::query()->where('type', 'prompt_published')->count())->toBe(1)
        ->and(\App\Models\FeedEvent::query()->where('type', 'badge_earned')->count())->toBe(1);
});

test('rating received pays the creator 25 xp', function () {
    $creator = User::factory()->create();
    $prompt = Prompt::factory()->for($creator, 'creator')->create();

    Rating::query()->create([
        'user_id' => User::factory()->create()->id,
        'prompt_id' => $prompt->id,
        'score' => 5,
    ]);

    expect((int) $creator->refresh()->xp)->toBe(25);
});

test('level chip renders on the public feed', function () {
    $creator = User::factory()->create(['xp' => 5000]);

    // Create as draft, then PUBLISH — the feed event comes from the
    // status transition (the updated observer), not the initial create.
    $prompt = Prompt::factory()->for($creator, 'creator')->draft()->create();
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $html = $this->get(route('feed.index'))->getContent();

    expect($html)->toContain('Lv 5');
});
