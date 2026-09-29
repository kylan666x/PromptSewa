<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * T3 (v1.5.0): admin account switching.
 *
 * start  — admin-only; opens a session AS the target (session carries
 *          impersonator_id; the real admin id never leaves the session).
 * stop   — ends the active session and restores the admin identity.
 *
 * Rules enforced here: admins only, no nesting, self-switch is a no-op
 * row, every start/end writes/updates a row (session log — updates to
 * ended_at allowed; this is not a financial ledger).
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user)
    {
        $admin = $request->user();
        abort_unless($admin?->isAdmin(), 403, 'Only admins can switch accounts.');

        // Nesting refused: an admin already acting as someone else cannot
        // start another impersonation.
        abort_if($request->session()->has('impersonator_id'), 403, 'Already impersonating — return first.');

        // Self-switch: log a no-op row (started+ended immediately) and
        // bounce back without touching the session identity.
        if ($user->id === $admin->id) {
            Impersonation::create([
                'impersonator_id' => $admin->id,
                'target_id' => $user->id,
                'started_at' => now(),
                'ended_at' => now(),
            ]);

            return back()->with('info', 'That is your own account — nothing to switch.');
        }

        Impersonation::create([
            'impersonator_id' => $admin->id,
            'target_id' => $user->id,
            'started_at' => now(),
        ]);

        $request->session()->put('impersonator_id', $admin->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with('info', 'You are now acting as @'.$user->username.' — the bar below stays visible until you return.');
    }

    public function stop(Request $request)
    {
        $impersonatorId = (int) $request->session()->get('impersonator_id');
        abort_unless($impersonatorId > 0, 403, 'Not impersonating.');

        // Close the open session row (ended_at update — session log).
        $session = Impersonation::activeBy($impersonatorId);
        if ($session !== null && $session->target_id === $request->user()?->id) {
            $session->forceFill(['ended_at' => now()])->save();
        }

        $admin = User::query()->find($impersonatorId);
        abort_if($admin === null || ! $admin->isAdmin(), 403, 'Original account is no longer an admin.');

        $request->session()->forget('impersonator_id');
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Welcome back, '.$admin->name.'.');
    }
}
