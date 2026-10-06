<?php

use App\Models\Prompt;
use App\Models\Rating;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/**
 * S3 (v1.8.0) — the earn loop: engagement rewards are bounded,
 * idempotent and SPEND-ONLY.
 *
 * Locks: publish fires once (double-save no-op), rating-received credits
 * the creator, daily-visit credits once per day through the web
 * middleware, the same-day cap stops further rewards, an explicit legacy
 * off row stops everything (S1 v1.9.0: the switch ships RETIRED and ON),
 * and every engagement row is cash-ineligible by construction.
 */
function engagementEnableSikka(): void
{
    app(SettingsService::class)->set('sikka_enabled', '1');
}

function engagementRows(User $user): Collection
{
    return SikkaTransaction::query()
        ->where('user_id', $user->id)
        ->where('type', SikkaTransaction::TYPE_ENGAGEMENT_REWARD)
        ->get();
}

test('publishing credits one engagement reward, spend-only, and a re-save never double-fires', function () {
    engagementEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->draft()->create(['user_id' => $creator->id]);

    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $rows = engagementRows($creator);

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row->amount_sikka)->toBe(2) // engage_publish_sikka default
        ->and($row->cashout_eligible)->toBeFalse()
        ->and($row->idempotency_key)->toBe('engage:'.$creator->id.':publish:'.now()->toDateString())
        ->and($row->meta['engagement'])->toBe('publish');

    // A re-save of the already-published prompt emits nothing new.
    $prompt->update(['title' => 'Re-saved title']);

    expect(engagementRows($creator))->toHaveCount(1);

    $sikka = app(SikkaService::class);

    expect($sikka->spendableAvailable($creator))->toBe(2)
        ->and($sikka->cashoutableAvailable($creator))->toBe(0); // spend-only lock
});

test('a rating received credits the prompt creator', function () {
    engagementEnableSikka();

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->create(['user_id' => $creator->id]);
    $rater = User::factory()->create();

    Rating::query()->create(['user_id' => $rater->id, 'prompt_id' => $prompt->id, 'score' => 5]);

    $rows = engagementRows($creator);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->amount_sikka)->toBe(1)
        ->and($rows->first()->cashout_eligible)->toBeFalse()
        ->and($rows->first()->idempotency_key)->toBe('engage:'.$creator->id.':rating_received:'.now()->toDateString());
});

test('a daily visit credits once per day through the web middleware', function () {
    engagementEnableSikka();

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))->assertOk();
    $this->actingAs($user)->get(route('home'))->assertOk();

    $rows = engagementRows($user);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->amount_sikka)->toBe(1)
        ->and($rows->first()->idempotency_key)->toBe('engage:'.$user->id.':daily_visit:'.now()->toDateString());
});

test('the same-day cap stops further rewards, and the emitter amount can be turned off', function () {
    engagementEnableSikka();

    $settings = app(SettingsService::class);
    $settings->set('engage_daily_cap_sikka', '2');
    $settings->set('engage_daily_sikka', '1');
    $settings->set('engage_publish_sikka', '2');
    $settings->set('engage_rating_sikka', '1');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->draft()->create(['user_id' => $creator->id]);

    // Publish first: 2 Sikka lands the day exactly at the cap.
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    // Both later emitters are over the bounded cap and must be skipped.
    $sikka = app(SikkaService::class);
    expect($sikka->rewardEngagement($creator, SikkaService::ENGAGEMENT_DAILY_VISIT))->toBeNull()
        ->and($sikka->rewardEngagement($creator, SikkaService::ENGAGEMENT_RATING_RECEIVED))->toBeNull();

    expect(engagementRows($creator))->toHaveCount(1)
        ->and($sikka->spendableAvailable($creator))->toBe(2);

    // An amount of 0 turns an emitter off for the day.
    $settings->set('engage_publish_sikka', '0');

    $other = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $second = Prompt::factory()->draft()->create(['user_id' => $other->id]);
    $second->update(['status' => Prompt::STATUS_PUBLISHED]);

    expect(engagementRows($other))->toHaveCount(0);
});

test('the switch ships retired and ON, an explicit legacy off row still gates, and unknown emitters throw', function () {
    $user = User::factory()->create(['role' => User::ROLE_CREATOR]);

    // S1 (v1.9.0): no desk flip required — the emitter credits out of the box.
    expect(app(SikkaService::class)->rewardEngagement($user, SikkaService::ENGAGEMENT_PUBLISH))
        ->not->toBeNull();

    // An unknown emitter is a programming error, never a silent no-op.
    expect(fn () => app(SikkaService::class)->rewardEngagement($user, 'not-an-emitter'))
        ->toThrow(InvalidArgumentException::class);

    // An explicit legacy '0' row (no UI writes one any more) still stops the
    // ledger — backward compatibility, not a supported switch.
    app(SettingsService::class)->set('sikka_enabled', '0');

    $gated = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->draft()->create(['user_id' => $gated->id]);
    $prompt->update(['status' => Prompt::STATUS_PUBLISHED]);

    $this->actingAs($gated)->get(route('home'))->assertOk();

    expect(engagementRows($gated))->toHaveCount(0);
});
