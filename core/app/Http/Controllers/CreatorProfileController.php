<?php

namespace App\Http\Controllers;

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
        $prompts = $creator->prompts()
            ->publicListing()
            ->with(['category', 'latestVersion'])
            ->withCount('versions')
            ->latest('sales_count')
            ->latest()
            ->paginate(12);

        $stats = [
            'prompts' => $creator->prompts()->publicListing()->count(),
            'total_sales' => (int) $creator->prompts()->publicListing()->sum('sales_count'),
            'joined' => $creator->created_at,
        ];

        return view('creators.show', [
            'creator' => $creator,
            'prompts' => $prompts,
            'stats' => $stats,
        ]);
    }
}
