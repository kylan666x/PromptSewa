<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\PurgeDemoAccounts;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * T10 (v1.5.0): demo purge panel — Admin → Users.
 *
 * Dry-run preview table (exact PurgeDemoAccounts plan) + a force button.
 * The actual deletion/quarantine logic lives ONLY in the pv:purge-demo
 * command; the panel shells it so the runbook and the UI can never drift.
 * Admin-only (moderators 403) — same guard as role changes.
 */
class UserPurgeController extends Controller
{
    public function preview(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can run the demo purge.');

        $command = app(PurgeDemoAccounts::class);
        // Capture the command's line output by running it through the
        // console output buffer (dry-run writes nothing).
        $exit = \Illuminate\Support\Facades\Artisan::call('pv:purge-demo', ['--dry-run' => true]);
        $output = trim(\Illuminate\Support\Facades\Artisan::output());

        return back()->with('purge_preview', $output)
            ->with('purge_exit', $exit);
    }

    public function run(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can run the demo purge.');

        // Force writes: hard deletes + quarantine, with the same audit log
        // the CLI writes (storage/logs/purge-demo.log).
        $exit = \Illuminate\Support\Facades\Artisan::call('pv:purge-demo', ['--force' => true]);
        $output = trim(\Illuminate\Support\Facades\Artisan::output());

        return back()->with('purge_preview', $output)
            ->with($exit === 0 ? 'success' : 'error', $exit === 0
                ? 'Demo purge applied — see the log below.'
                : 'Demo purge FAILED (exit '.$exit.') — nothing assumed; review the log.');
    }
}
