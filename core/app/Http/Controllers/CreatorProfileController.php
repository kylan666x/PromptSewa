<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\User;

/**
 * Public creator profile: identity, bio and the creator's published
 * prompts. Safe for guests — only public listings are exposed.
 */
class CreatorProfileController extends Controller
{
    // {creator} binds by username (name fallback) in AppServiceProvider::boot;
    // soft-deleted accounts are excluded at the binding level.
    public function show(User $creator)
    {
        // Hotfix directive: the profile hero + prompt cards need activeFrame
        // loaded or the frame overlay never renders (the relation lazily
        // loads too, but the eager load keeps the N+1 away on card grids).
        $creator->loadMissing('activeFrame');

        // F1 (v1.9.2): order by the REAL sales count (paid order lines), not
        // the legacy `sales_count` column nothing ever increments — the whole
        // catalog used to sort as if every listing had zero sales.
        $prompts = $creator->prompts()
            ->publicListing()
            ->with(['category', 'latestVersion', 'creator.activeFrame'])
            ->withCount(['versions', 'paidSales'])
            ->orderByDesc('paid_sales_count')
            ->latest()
            ->paginate(12);

        // H1 (v1.5.2): the public profile stat counts PUBLISHED listings only
        // (publicListing scope = published + public). The admin Users column
        // shows the total across all statuses — two different numbers on
        // purpose, both locked by CreatorProfileCountTest. The v1.5.2 adoption
        // reconciliation: 270 published pre-adoption vs 268+1=269 published
        // after — the delta of one prompt is a single draft/pending/rejected
        // listing among the 276 adopted rows (published-count differs from
        // adopted-count by non-published statuses; recorded here, NOT fixed).
        $stats = [
            'prompts' => $creator->prompts()->publicListing()->count(),
            // F1 (v1.9.2): the public profile's sales stat reads the same
            // paid-lines truth as the creator's own dashboard (they used to
            // be two different sums of a dead column — both zero).
            'total_sales' => OrderItem::paidSalesCountForCreator($creator),
            'joined' => $creator->created_at,
        ];

        // P2b (v1.7.1): user-facing Achievements — earned badges (oldest
        // first, so the wall reads as a history) + the Lv chip.
        $earnedBadges = $creator->userBadges()->with('badge')->orderBy('awarded_at')->get();

        return view('creators.show', [
            'creator' => $creator,
            'prompts' => $prompts,
            'stats' => $stats,
            'earnedBadges' => $earnedBadges,
        ]);
    }
}
