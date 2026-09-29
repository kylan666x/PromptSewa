<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * T3 (v1.5.0): effective-user resolution for admin impersonation.
 *
 * Runs BEFORE auth-dependent logic on web routes. If the session carries
 * `impersonator_id`, the logged-in user IS the target — the admin's
 * identity is restored only via the explicit "Return" action (which
 * re-logins the impersonator). Powers are NOT merged: an impersonated
 * session has exactly the target's permissions. The chrome bar is the
 * only reminder of who is really driving.
 */
class ResolveImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Guard against a stale impersonator entry (e.g. the original
        // admin lost admin rights mid-session): silently end the switch.
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId !== null) {
            $admin = User::query()->find($impersonatorId);

            if ($admin === null || ! $admin->isAdmin() || $request->user()?->id === null) {
                $request->session()->forget('impersonator_id');
            }
        }

        return $next($request);
    }
}
