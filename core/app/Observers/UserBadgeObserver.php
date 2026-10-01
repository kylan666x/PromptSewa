<?php

namespace App\Observers;

use App\Models\FeedEvent;
use App\Models\UserBadge;
use App\Services\GamificationService;

/**
 * G2/G4 (v1.7.0) — badge_earned feed emission + the +200 XP. Note: the XP
 * for earning the badge is granted here (observer on the award row), so
 * GamificationService::awardBadge stays a pure award primitive and manual
 * admin awards pay XP identically to auto awards.
 */
class UserBadgeObserver
{
    public function created(UserBadge $userBadge): void
    {
        $user = $userBadge->user;
        $badge = $userBadge->badge;

        if ($user === null || $badge === null) {
            return;
        }

        app(GamificationService::class)->grantXp($user, 'badge_earned');

        $dedupeKey = "badge_earned:{$user->id}:{$badge->id}";

        if (FeedEvent::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        FeedEvent::query()->create([
            'type' => FeedEvent::TYPE_BADGE_EARNED,
            'actor_id' => $user->id,
            'subject_type' => $badge::class,
            'subject_id' => $badge->id,
            'meta' => [
                'badge_name' => $badge->name,
                'badge_slug' => $badge->slug,
                'badge_image' => $badge->image_path,
            ],
            'dedupe_key' => $dedupeKey,
            'created_at' => now(),
        ]);
    }
}
