<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptReport;
use Illuminate\Http\Request;

/**
 * Admin triage of "Report this prompt" submissions: review, resolve
 * (action taken) or dismiss (no action needed).
 */
class PromptReportAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', PromptReport::STATUS_OPEN);
        $validStatuses = ['', PromptReport::STATUS_OPEN, PromptReport::STATUS_RESOLVED, PromptReport::STATUS_DISMISSED];

        if (! in_array($status, $validStatuses, true)) {
            $status = PromptReport::STATUS_OPEN;
        }

        $reports = PromptReport::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['prompt', 'reporter'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => PromptReport::count(),
            PromptReport::STATUS_OPEN => PromptReport::where('status', PromptReport::STATUS_OPEN)->count(),
            PromptReport::STATUS_RESOLVED => PromptReport::where('status', PromptReport::STATUS_RESOLVED)->count(),
            PromptReport::STATUS_DISMISSED => PromptReport::where('status', PromptReport::STATUS_DISMISSED)->count(),
        ];

        return view('admin.reports', [
            'reports' => $reports,
            'counts' => $counts,
            'currentStatus' => $status,
        ]);
    }

    public function updateStatus(Request $request, PromptReport $report)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.PromptReport::STATUS_RESOLVED.','.PromptReport::STATUS_DISMISSED.','.PromptReport::STATUS_OPEN],
        ]);

        $report->update([
            'status' => $validated['status'],
            'resolved_by' => $validated['status'] === PromptReport::STATUS_OPEN ? null : $request->user()->id,
            'resolved_at' => $validated['status'] === PromptReport::STATUS_OPEN ? null : now(),
        ]);

        return back()->with('report_status_updated', true);
    }
}
