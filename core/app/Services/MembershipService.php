<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Frame;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Notification;
use App\Models\Order;
use App\Models\UserFrameUnlock;

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

    // -------------------------------------------------------------
    // Activation (S5) — called from the paid transition (OrderObserver)
    // -------------------------------------------------------------

    /**
     * Activate every membership plan line on a PAID order: one membership
     * row, its FIRST stipend (period 0) and the plan's perk grants — all
     * inside the settlement transaction, all idempotent:
     *
     *   - a (source_order_id, plan_id) membership that already exists is
     *     skipped (double approval, an observer re-entry, a replay);
     *   - the first stipend rides the UNIQUE `stipend:{id}:0` key;
     *   - the badge award is idempotent inside GamificationService, the
     *     frame unlock through firstOrCreate, and the verified flip only
     *     writes when the flag actually changes.
     *
     * @return int memberships created by THIS call
     */
    public function activateForOrder(Order $order): int
    {
        $created = 0;

        $order->items()->with('membershipPlan')->get()->each(function ($item) use ($order, &$created): void {
            $plan = $item->membershipPlan;

            if ($plan === null) {
                return; // not a membership line
            }

            $existing = Membership::query()
                ->where('source_order_id', $order->id)
                ->where('plan_id', $plan->id)
                ->exists();

            if ($existing) {
                return; // idempotent replay — never a second membership
            }

            $membership = Membership::query()->create([
                'user_id' => $order->buyer_id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => now()->addDays(max(1, (int) $plan->duration_days)),
                'status' => Membership::STATUS_ACTIVE,
                'source_order_id' => $order->id,
            ]);

            // First stipend (period 0) — keyed, so a replay grants nothing.
            $this->sikka->grantStipend($membership, 0);

            $this->grantPerks($membership, $plan);

            $created++;
        });

        return $created;
    }

    /**
     * Grant a plan's perks through the existing choke points: badges via
     * GamificationService (reason `membership:{slug}`), frames as an
     * audited unlock row, the verified flag as a direct flip. Each grant
     * is idempotent AND notifies only when it actually changed something.
     */
    public function grantPerks(Membership $membership, MembershipPlan $plan): void
    {
        $user = $membership->user;

        if ($user === null) {
            return;
        }

        $reason = 'membership:'.$plan->slug;

        $badgeId = $plan->perk(MembershipPlan::PERK_BADGE_ID);
        if ($badgeId !== null && ($badge = Badge::query()->find($badgeId)) !== null) {
            // Idempotent inside the service; its observer emits the bell row.
            app(GamificationService::class)->awardBadge($user, $badge, null, $reason);
        }

        $frameId = $plan->perk(MembershipPlan::PERK_FRAME_ID);
        if ($frameId !== null && ($frame = Frame::query()->find($frameId)) !== null) {
            $unlock = UserFrameUnlock::query()->firstOrCreate(
                ['user_id' => $user->id, 'frame_id' => $frame->id],
                ['source' => UserFrameUnlock::SOURCE_MEMBERSHIP, 'reason' => $reason],
            );

            if ($unlock->wasRecentlyCreated) {
                Notification::emit(
                    $user,
                    Notification::TYPE_FRAME_UNLOCKED,
                    "The \"{$frame->name}\" frame was unlocked by your {$plan->name} membership.",
                    $frame,
                );
            }
        }

        if ($plan->perk(MembershipPlan::PERK_GRANT_VERIFIED) && ! $user->is_verified) {
            $user->is_verified = true;
            $user->save();

            Notification::emit(
                $user,
                Notification::TYPE_VERIFIED_GRANTED,
                "Your account was verified by your {$plan->name} membership.",
                $membership,
            );
        }
    }

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
