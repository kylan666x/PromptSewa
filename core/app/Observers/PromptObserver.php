<?php

namespace App\Observers;

use App\Models\FeedEvent;
use App\Models\Prompt;
use App\Services\GamificationService;
use App\Services\SikkaService;

/**
 * G2/G4 (v1.7.0) — publish-side gamification.
 *
 * Runs inside the triggering transaction (Eloquent events fire within the
 * caller's DB::transaction) — the feed event and the badge award commit
 * or roll back WITH the publish. Never emit from controllers (§6).
 */
class PromptObserver
{
    public function updated(Prompt $prompt): void
    {
        // Fire only on the draft/pending → published transition.
        if ($prompt->status !== Prompt::STATUS_PUBLISHED) {
            return;
        }

        if ($prompt->getOriginal('status') === Prompt::STATUS_PUBLISHED) {
            return;
        }

        $user = $prompt->creator;
        if ($user === null) {
            return;
        }

        $gamification = app(GamificationService::class);

        // XP first (publish pays 50), then criteria evaluation.
        $gamification->grantXp($user, 'publish');
        $gamification->evaluateCriteria($user, 'first_publish');

        // S3 (v1.8.0): publishing earns an engagement reward — spend-only,
        // idempotent per (creator, day) type, bounded by the daily cap.
        app(SikkaService::class)->rewardEngagement(
            $user,
            SikkaService::ENGAGEMENT_PUBLISH,
        );

        $this->emitPublished($prompt, $user->id);
    }

    /** Feed emission with a STABLE dedupe key (one event per prompt ever). */
    private function emitPublished(Prompt $prompt, int $actorId): void
    {
        // One publish event per prompt LIFETIME — re-saves of an already-
        // published listing never re-announce it (stable key, no updated_at).
        $dedupeKey = "prompt_published:{$prompt->id}";

        // Idempotent per publish: the same publish re-fired (observer
        // re-entry after version appends) writes once.
        if (FeedEvent::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        FeedEvent::query()->create([
            'type' => FeedEvent::TYPE_PROMPT_PUBLISHED,
            'actor_id' => $actorId,
            'subject_type' => $prompt::class,
            'subject_id' => $prompt->id,
            'meta' => [
                'title' => $prompt->title,
                'slug' => $prompt->slug,
                'type' => $prompt->type,
                'price_npr' => intdiv($prompt->price_cents, 100),
            ],
            'dedupe_key' => $dedupeKey,
            'created_at' => now(),
        ]);
    }
}
