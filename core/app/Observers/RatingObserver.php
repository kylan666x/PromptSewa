<?php

namespace App\Observers;

use App\Models\Rating;
use App\Services\GamificationService;

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
    }
}
