<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\OrderItem;
use App\Models\Prompt;
use App\Models\User;

/**
 * W4 (v1.7.3) — ONE threshold source for badge AND frame criteria.
 *
 * The frames/badges admin surfaces and the profile picker's lock chips all
 * read from this evaluator — the trigger values are never duplicated in a
 * second file, or the two gamification surfaces will drift.
 *
 * top_rated is staff-judged (no automatic threshold) — evaluate() never
 * grants it; progress() reports null and the UI shows it as such.
 */
class CriterionEvaluator
{
    /** Criteria shared by badges and frames. */
    public const CRITERIA = [
        'first_publish',
        'first_sale',
        'sales_10',
        'sales_50',
        'verified',
        'top_rated',
        // Frames only: granted by an admin with a mandatory reason.
        'manual',
    ];

    /**
     * F3 (v1.7.8) — one-line, human descriptions for the admin criterion
     * selects. Staff-judged and manual criteria say so outright, so an
     * admin never wonders why a badge never auto-awards.
     */
    public const DESCRIPTIONS = [
        'first_publish' => 'Auto: first published listing goes live.',
        'first_sale' => 'Auto: first paid sale settles.',
        'sales_10' => 'Auto: 10 total sales across published listings.',
        'sales_50' => 'Auto: 50 total sales across published listings.',
        'verified' => 'Auto: an admin grants the verified check.',
        'top_rated' => 'Staff-judged: never auto-awards - award it by hand.',
        'manual' => 'Manual only: never auto-awards - grant it by hand.',
    ];

    /** Real trigger values per automatic criterion. */
    public const GOALS = [
        'first_publish' => 1,
        'first_sale' => 1,
        'sales_10' => 10,
        'sales_50' => 50,
        'verified' => 1,
    ];

    /**
     * Current count for a criterion — the {have} side of the progress chip.
     * top_rated returns null (staff-judged, no number is honest).
     */
    public static function progress(User $user, string $criterion): ?int
    {
        return match ($criterion) {
            'first_publish' => min($user->prompts()->where('status', Prompt::STATUS_PUBLISHED)->count(), 1),
            // F1 (v1.9.2): one sales truth for the whole product. The old
            // `sum('sales_count')` read a column with no writer, so every
            // creator was permanently stuck at 0/1, 0/10 and 0/50 — the
            // first_sale and sales_10 badges could never auto-award, however
            // many credits they had actually earned.
            'first_sale' => min(OrderItem::paidSalesCountForCreator($user), 1),
            'sales_10' => min(OrderItem::paidSalesCountForCreator($user), 10),
            'sales_50' => min(OrderItem::paidSalesCountForCreator($user), 50),
            'verified' => $user->is_verified ? 1 : 0,
            'top_rated' => null,
            default => null,
        };
    }

    /** Goal for a criterion — the {need} side. Null = staff-judged/manual. */
    public static function goal(string $criterion): ?int
    {
        return self::GOALS[$criterion] ?? null;
    }

    /**
     * Has the user met a criterion? manual/unknown criteria are never
     * auto-met (they require an admin grant row).
     */
    public static function isMet(User $user, string $criterion): bool
    {
        if (! isset(self::GOALS[$criterion])) {
            return false;
        }

        $progress = self::progress($user, $criterion);

        return $progress !== null && $progress >= self::GOALS[$criterion];
    }

    /**
     * All automatic criteria the user currently satisfies (badge-relevant
     * subset only — 'manual' is excluded by construction).
     *
     * @return list<string>
     */
    public static function metCriteria(User $user): array
    {
        return collect(Badge::CRITERIA)
            ->filter(fn (string $criterion) => self::isMet($user, $criterion))
            ->values()
            ->all();
    }
}
