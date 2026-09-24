<?php

namespace App\Http\Controllers;

use App\Models\User;

/**
 * Public creator profile: identity, bio and the creator's published
 * prompts. Safe for guests — only public listings are exposed.
 */
class CreatorProfileController extends Controller
{
    public function show(User $user)
    {
        // Soft-deleted (banned) accounts are not public profiles.
        abort_if($user->deleted_at !== null, 404);

        $prompts = $user->prompts()
            ->publicListing()
            ->with(['category', 'latestVersion'])
            ->withCount('versions')
            ->latest('sales_count')
            ->latest()
            ->paginate(12);

        $stats = [
            'prompts' => $user->prompts()->publicListing()->count(),
            'total_sales' => (int) $user->prompts()->publicListing()->sum('sales_count'),
            'joined' => $user->created_at,
        ];

        return view('creators.show', [
            'creator' => $user,
            'prompts' => $prompts,
            'stats' => $stats,
        ]);
    }
}
