<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\OrderItem;
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
        $tabParam = $request->query('tab');
        $tab = in_array($tabParam, ['saved', 'stats', 'feed', 'achievements'], true) ? $tabParam : 'prompts';

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

        // G5 (v1.7.0): Stats tab series (30-day, file-cached 10 min).
        // Empty series is a first-class state — 30 zeros, never sparse.
        $analytics = $tab === 'stats' ? app(\App\Services\AnalyticsService::class) : null;

        // G4 (v1.7.0): Feed tab — own events + global milestones.
        $feedEvents = $tab === 'feed'
            ? \App\Models\FeedEvent::query()
                ->publicStream()
                ->with('actor.activeFrame') // W1: feed actors render the overlay.
                ->where(function ($q) use ($user) {
                    $q->where('actor_id', $user->id)
                        ->orWhere('type', \App\Models\FeedEvent::TYPE_SALE_MILESTONE);
                })
                ->paginate(20, pageName: 'feed_page')
            : null;

        // P2b (v1.7.1): Achievements tab — earned wall + REAL progress rows
        // computed from actual counts. No fabricated thresholds, no hidden
        // badges: every criterion with an active badge definition shows its
        // true current number against its real trigger value.
        $achievements = null;

        if ($tab === 'achievements') {
            $earned = $user->userBadges()->with('badge')->orderByDesc('awarded_at')->get();

            // F1 (v1.9.2): the REAL sales count — paid order lines naming the
            // creator's listings (both rails). The old `sum('sales_count')`
            // read a column nothing ever writes, so this was 0 forever.
            $salesCount = OrderItem::paidSalesCountForCreator($user);
            $publishedCount = $user->prompts()->where('status', Prompt::STATUS_PUBLISHED)->count();

            // Real trigger values per criterion (OrderObserver semantics).
            $progressMap = [
                'first_publish' => ['label' => 'Publish your first prompt', 'current' => min($publishedCount, 1), 'goal' => 1],
                'first_sale' => ['label' => 'Make your first sale', 'current' => min($salesCount, 1), 'goal' => 1],
                'sales_10' => ['label' => 'Reach 10 sales', 'current' => min($salesCount, 10), 'goal' => 10],
                'sales_50' => ['label' => 'Reach 50 sales', 'current' => min($salesCount, 50), 'goal' => 50],
                'verified' => ['label' => 'Get verified', 'current' => $user->is_verified ? 1 : 0, 'goal' => 1],
                'top_rated' => ['label' => 'Earn a top rating', 'current' => null, 'goal' => null], // staff-judged
            ];

            $earnedByCriterion = $earned->filter(fn ($b) => $b->badge !== null)
                ->mapWithKeys(fn ($b) => [$b->badge->criterion => $b]);

            $achievements = [
                'earned' => $earned,
                'progress' => collect(\App\Models\Badge::CRITERIA)
                    ->map(fn (string $criterion) => [
                        'criterion' => $criterion,
                        'earned' => $earnedByCriterion->has($criterion),
                        'label' => $progressMap[$criterion]['label'] ?? ucfirst(str_replace('_', ' ', $criterion)),
                        'current' => $progressMap[$criterion]['current'] ?? null,
                        'goal' => $progressMap[$criterion]['goal'] ?? null,
                    ])
                    ->values(),
            ];
        }

        return view('dashboard', [
            'prompts' => $prompts,
            'stats' => $stats,
            'tab' => $tab,
            'saved' => $saved,
            'viewsSeries' => $analytics?->viewsSeries(),
            'salesSeries' => $analytics?->salesSeries($user, countOnly: true),
            'ratingSeries' => $analytics?->ratingSeries($user),
            // F1 (v1.9.2): each row's sales number is the same paid-lines
            // truth (withCount), never the dead legacy column.
            'topPrompts' => $analytics !== null
                ? $user->prompts()->published()
                    ->withCount('paidSales')
                    ->orderByDesc('views_count')
                    ->limit(5)
                    ->get(['id', 'title', 'slug', 'views_count'])
                : null,
            'feedEvents' => $feedEvents,
            'achievements' => $achievements,
        ]);
    }
}
