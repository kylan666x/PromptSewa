<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PromptReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($report, $validated, $request) {
            $report->update([
                'status' => $validated['status'],
                'resolved_by' => $validated['status'] === PromptReport::STATUS_OPEN ? null : $request->user()->id,
                'resolved_at' => $validated['status'] === PromptReport::STATUS_OPEN ? null : now(),
            ]);

            // F6 (v1.7.8): notify the reporter (guests have no bell row) —
            // resolved vs dismissed are different outcomes, never merged.
            $decided = in_array($validated['status'], [PromptReport::STATUS_RESOLVED, PromptReport::STATUS_DISMISSED], true);

            if ($decided && $report->reporter !== null) {
                $resolved = $validated['status'] === PromptReport::STATUS_RESOLVED;

                Notification::emit(
                    $report->reporter,
                    $resolved ? Notification::TYPE_REPORT_RESOLVED : Notification::TYPE_REPORT_DISMISSED,
                    $resolved
                        ? 'Your report was resolved — thank you.'
                        : 'Your report was reviewed and dismissed.',
                    $report,
                );
            }
        });

        return back()->with('report_status_updated', true);
    }
}
