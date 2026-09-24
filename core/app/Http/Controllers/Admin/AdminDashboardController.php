<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin panel home: platform-wide totals, moderation queue and the latest
 * manual payments awaiting verification.
 */
class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'prompts' => Prompt::count(),
            'published' => Prompt::where('status', Prompt::STATUS_PUBLISHED)->count(),
            'pending' => Prompt::where('status', Prompt::STATUS_PENDING)->count(),
            'users' => User::count(),
            'creators' => Prompt::distinct()->count('user_id'),
            'orders' => Order::count(),
            'revenue_paisa' => (int) Order::query()->where('status', Order::STATUS_PAID)->sum('total_paisa'),
            'open_reports' => PromptReport::where('status', PromptReport::STATUS_OPEN)->count(),
        ];

        $reviewQueue = Prompt::query()
            ->where('status', Prompt::STATUS_PENDING)
            ->with(['creator', 'category'])
            ->latest()
            ->take(8)
            ->get();

        $manualQueue = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->whereNotNull('payment_reference')
            ->with(['buyer', 'items.pack'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'reviewQueue' => $reviewQueue,
            'manualQueue' => $manualQueue,
        ]);
    }
}
