<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * Creator dashboard: overview stats + the creator's own prompt table
 * (all statuses and visibilities — owners always see their own listings).
 * T13 (v1.5.0): ?tab=saved renders the bookmarked-prompts tab.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'saved' ? 'saved' : 'prompts';

        $prompts = $user->prompts()
            ->with(['category', 'latestVersion'])
            ->withCount('versions')
            ->latest()
            ->paginate(12);

        // T13: the Saved tab lists bookmarked listings (own prompts may be
        // bookmarked too; a saved private prompt stays visible only here —
        // visibility rules on public pages are untouched).
        $saved = Bookmark::query()
            ->where('user_id', $user->id)
            ->with(['prompt.category', 'prompt.latestVersion'])
            ->latest()
            ->paginate(12, pageName: 'saved_page');

        // Stats are computed over the creator's whole catalog, not just
        // the current page.
        $stats = [
            'total' => $user->prompts()->count(),
            'published' => $user->prompts()->where('status', Prompt::STATUS_PUBLISHED)->count(),
            'pending' => $user->prompts()->where('status', Prompt::STATUS_PENDING)->count(),
            'draft' => $user->prompts()->where('status', Prompt::STATUS_DRAFT)->count(),
        ];

        return view('dashboard', [
            'prompts' => $prompts,
            'stats' => $stats,
            'tab' => $tab,
            'saved' => $saved,
        ]);
    }
}
