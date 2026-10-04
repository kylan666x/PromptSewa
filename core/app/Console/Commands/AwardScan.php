<?php

namespace App\Console\Commands;

use App\Services\GamificationService;
use Illuminate\Console\Command;

/**
 * F3 (v1.7.8) — badge criterion backfill.
 *
 * Users who met a criterion before its badge existed (or before the
 * criterion had an emitter) are invisible to the event observers — this
 * scan evaluates every automatic criterion for every user and awards the
 * misses. Idempotent by construction: awardBadge honours the
 * UNIQUE(user_id, badge_id) gate, so the daily run is a cheap no-op once
 * everyone is caught up.
 */
class AwardScan extends Command
{
    protected $signature = 'pv:award-scan';

    protected $description = 'Award missed badge criteria for every user (idempotent backfill)';

    public function handle(GamificationService $gamification): int
    {
        $stats = $gamification->scanAll();

        $this->info("Scanned {$stats['users']} user(s) - awarded {$stats['awarded']} badge(s).");

        return self::SUCCESS;
    }
}
