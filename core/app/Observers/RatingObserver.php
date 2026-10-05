<?php

namespace App\Observers;

use App\Models\Rating;
use App\Services\GamificationService;
use App\Services\SikkaService;

/**
 * G2 (v1.7.0) — a rating received pays the prompt's creator +25 XP.
 */
class RatingObserver
{
    public function created(Rating $rating): void
    {
        $creator = $rating->prompt?->creator;

        if ($creator === null) {
            return;
        }

        app(GamificationService::class)->grantXp($creator, 'rating_received');

        // S3 (v1.8.0): a rating received earns an engagement reward —
        // spend-only, one per (creator, day), bounded by the daily cap.
        app(SikkaService::class)->rewardEngagement(
            $creator,
            SikkaService::ENGAGEMENT_RATING_RECEIVED,
        );
    }
}
