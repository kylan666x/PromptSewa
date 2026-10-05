<?php

namespace App\Services;

use App\Models\Membership;

/**
 * S3/S5 (v1.8.0) — membership lifecycle orchestration.
 *
 * The stipend/expiry scan is the scheduled half of the membership
 * contract (pv:stipend-scan, cron-tolerant, idempotent):
 *
 *   - every ACTIVE membership grants one membership_stipend row per
 *     elapsed 30-day period, keyed `stipend:{membership_id}:{period}` —
 *     the keys make any replay (double cron, manual rerun, a catch-up
 *     after downtime) grant each period exactly once;
 *   - a membership whose ends_at has passed flips to expired — a STATE
 *     change on the membership row only; ledgers are never rewritten;
 *   - expired memberships stop: the scan only walks active rows, so past
 *     the flip they can never accrue another stipend.
 *
 * Activation (S5) lives here too: activateForOrder() is called from the
 * paid transition (OrderObserver) for NPR-rail membership lines and runs
 * the first stipend + perk grants through the existing choke points.
 */
class MembershipService
{
    public function __construct(
        private readonly SikkaService $sikka,
    ) {}

    /**
     * The daily scan: catch up stipends, then expire lapsed memberships.
     * Cron-tolerant and replay-safe forever.
     *
     * @return array{memberships: int, stipends: int, expired: int}
     */
    public function scanStipends(): array
    {
        $stats = ['memberships' => 0, 'stipends' => 0, 'expired' => 0];

        Membership::query()
            ->with('plan')
            ->where('status', Membership::STATUS_ACTIVE)
            ->chunkById(200, function ($memberships) use (&$stats): void {
                foreach ($memberships as $membership) {
                    $stats['memberships']++;

                    if ($membership->plan === null) {
                        continue; // orphaned plan — nothing to grant, never fatal
                    }

                    foreach (range(0, $this->elapsedPeriods($membership) - 1) as $period) {
                        if ($this->sikka->grantStipend($membership, $period) !== null) {
                            $stats['stipends']++;
                        }
                    }

                    if ($membership->hasEnded()) {
                        $membership->transitionTo(Membership::STATUS_EXPIRED);
                        $stats['expired']++;
                    }
                }
            });

        return $stats;
    }

    /**
     * How many stipend periods have ELAPSED since the membership started?
     *
     * Period 0 is the activation period (granted at purchase, minutes
     * after starts_at), so a fresh membership is at 1; every full 30 days
     * adds one more, capped at the plan's whole duration so a lapsed
     * membership can never accrue beyond its term.
     */
    public function elapsedPeriods(Membership $membership): int
    {
        if ($membership->starts_at === null) {
            return 0;
        }

        $seconds = max(0, now()->getTimestamp() - $membership->starts_at->getTimestamp());
        $elapsed = intdiv($seconds, Membership::PERIOD_DAYS * 86400) + 1;

        $durationDays = max(1, (int) ($membership->plan?->duration_days ?? Membership::PERIOD_DAYS));
        $maxPeriods = max(1, intdiv($durationDays + Membership::PERIOD_DAYS - 1, Membership::PERIOD_DAYS));

        return min($elapsed, $maxPeriods);
    }
}
