<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * G2 (v1.7.0) — the single award choke point for badges and XP.
 *
 * Idempotency: the UNIQUE(user_id, badge_id) constraint on user_badges.
 * A double-fired observer insert throws a unique-violation inside the
 * triggering transaction and is caught+ignored HERE — never a second row,
 * never a broken outer transaction.
 *
 * XP grants are idempotent per (user, reason key) through the caller's
 * event semantics (publish/sale/badge events fire once by construction).
 *
 * Level = pure-PHP threshold function; no DB round trip, no cache.
 */
class GamificationService
{
    /** Level thresholds: cumulative XP required for each level. */
    public const LEVEL_THRESHOLDS = [
        1 => 0,
        2 => 500,
        3 => 1500,
        4 => 3000,
        5 => 5000,
        6 => 7500,
        7 => 10500,
        8 => 14000,
        9 => 18000,
        10 => 22500,
    ];

    /** XP awards per triggering event (G2 contract). */
    public const XP_AWARDS = [
        'publish' => 50,
        'sale' => 100,
        'rating_received' => 25,
        'badge_earned' => 200,
    ];

    /**
     * Award a badge to a user. Idempotent: a second call is a no-op.
     * Returns the UserBadge, or null when the user already holds it.
     */
    public function awardBadge(User $user, Badge $badge, ?User $awardedBy = null, ?string $reason = null): ?UserBadge
    {
        $existing = UserBadge::query()
            ->where('user_id', $user->id)
            ->where('badge_id', $badge->id)
            ->first();

        if ($existing !== null) {
            return null; // idempotent replay — nothing to do
        }

        try {
            // NOTE: the +200 badge_earned XP is granted by the UserBadge
            // observer (created event) — a single XP source for BOTH auto
            // and manual awards. This method must NOT also grant it, or a
            // double-count ships.
            return UserBadge::query()->create([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'awarded_by' => $awardedBy?->id,
                'reason' => $reason,
                'awarded_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return null; // concurrent double-fire — the constraint held
        }
    }

    /** Add XP for an event type. Pure integer math. */
    public function grantXp(User $user, string $event): int
    {
        $amount = self::XP_AWARDS[$event] ?? 0;

        if ($amount === 0) {
            return (int) $user->xp;
        }

        // Atomic increment, then refresh the in-memory value.
        DB::table('users')->where('id', $user->id)->increment('xp', $amount);
        $user->refresh();

        return (int) $user->xp;
    }

    /** Pure-PHP level for a given XP (no DB, no cache). */
    public static function levelForXp(int $xp): int
    {
        $level = 1;

        foreach (self::LEVEL_THRESHOLDS as $candidate => $threshold) {
            if ($xp >= $threshold) {
                $level = $candidate;
            }
        }

        return $level;
    }

    /** "Lv N" chip data for a user. */
    public static function levelChip(User $user): array
    {
        $xp = (int) $user->xp;

        return [
            'level' => self::levelForXp($xp),
            'xp' => $xp,
        ];
    }

    /**
     * Evaluate auto-award criteria for a user. Called by the observers;
     * each criterion maps to active badges which get awarded (idempotently).
     */
    public function evaluateCriteria(User $user, string $criterion): void
    {
        Badge::query()
            ->where('criterion', $criterion)
            ->where('is_active', true)
            ->get()
            ->each(fn (Badge $badge) => $this->awardBadge($user, $badge));
    }

    /**
     * F3 (v1.7.8) — backfill scan: evaluates every automatic criterion for
     * every user and awards matching active badges. Safe to run any time:
     * `awardBadge` skips existing rows (and re-checks the UNIQUE gate), so a
     * second run is a no-op. Used by `pv:award-scan` and the admin "Scan
     * now" button — one implementation, two doors.
     *
     * @return array{users: int, awarded: int}
     */
    public function scanAll(): array
    {
        $users = 0;
        $awarded = 0;

        User::query()->chunkById(200, function ($chunk) use (&$users, &$awarded) {
            foreach ($chunk as $user) {
                $users++;

                foreach (Badge::CRITERIA as $criterion) {
                    if (! CriterionEvaluator::isMet($user, $criterion)) {
                        continue;
                    }

                    Badge::query()
                        ->where('criterion', $criterion)
                        ->where('is_active', true)
                        ->get()
                        ->each(function (Badge $badge) use ($user, &$awarded) {
                            if ($this->awardBadge($user, $badge, null, 'Backfill scan (pv:award-scan)') !== null) {
                                $awarded++;
                            }
                        });
                }
            }
        });

        return ['users' => $users, 'awarded' => $awarded];
    }
}
