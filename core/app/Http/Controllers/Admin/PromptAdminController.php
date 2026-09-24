<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * Admin prompt moderation: browse everything, publish / reject / unpublish.
 */
class PromptAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');

        $prompts = Prompt::query()
            ->with(['creator', 'category', 'latestVersion'])
            ->when($status !== '' && in_array($status, [Prompt::STATUS_DRAFT, Prompt::STATUS_PENDING, Prompt::STATUS_PUBLISHED, Prompt::STATUS_REJECTED], true),
                fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all' => Prompt::count(),
            Prompt::STATUS_PENDING => Prompt::where('status', Prompt::STATUS_PENDING)->count(),
            Prompt::STATUS_PUBLISHED => Prompt::where('status', Prompt::STATUS_PUBLISHED)->count(),
            Prompt::STATUS_REJECTED => Prompt::where('status', Prompt::STATUS_REJECTED)->count(),
            Prompt::STATUS_DRAFT => Prompt::where('status', Prompt::STATUS_DRAFT)->count(),
        ];

        return view('admin.prompts', [
            'prompts' => $prompts,
            'counts' => $counts,
            'currentStatus' => $status,
        ]);
    }

    public function updateStatus(Request $request, Prompt $prompt)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', [Prompt::STATUS_PENDING, Prompt::STATUS_PUBLISHED, Prompt::STATUS_REJECTED])],
        ]);

        $prompt->fill(['status' => $validated['status']])->save();

        return back()->with('success', "\"{$prompt->title}\" is now {$validated['status']}.");
    }
}
