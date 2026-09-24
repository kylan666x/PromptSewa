<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * Creator dashboard: overview stats + the creator's own prompt table
 * (all statuses and visibilities — owners always see their own listings).
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $prompts = $user->prompts()
            ->with(['category', 'latestVersion'])
            ->withCount('versions')
            ->latest()
            ->paginate(12);

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
        ]);
    }
}
