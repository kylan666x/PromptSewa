<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * F3 (v1.5.2) — "Apply adoption" panel — Admin → Users.
 *
 * The founder runs the T4 catalog adoption from the browser instead of a
 * shell (no SSH on the cPanel host). Dry-run preview + force; the actual
 * reassignment logic lives ONLY in pv:adopt-catalog — the panel shells it
 * so runbook logic and UI can never drift (the UserPurgeController pattern).
 * Admin-only (moderators 403) — same guard as role changes.
 */
class AdoptionController extends Controller
{
    public function preview(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can run the catalog adoption.');

        $exit = Artisan::call('pv:adopt-catalog', ['--dry-run' => true]);
        $output = trim(Artisan::output());

        return back()->with('adoption_preview', $output)
            ->with('adoption_exit', $exit);
    }

    public function run(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can run the catalog adoption.');

        $exit = Artisan::call('pv:adopt-catalog', ['--force' => true]);
        $output = trim(Artisan::output());

        return back()->with('adoption_preview', $output)
            ->with($exit === 0 ? 'success' : 'error', $exit === 0
                ? 'Catalog adoption applied — all candidate prompts now belong to the official account.'
                : 'Catalog adoption FAILED (exit '.$exit.') — nothing assumed; review the output below.');
    }
}
