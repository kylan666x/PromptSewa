<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A4 ban/disable: a banned account keeps its data but loses access.
 * Any authenticated request from a banned user is logged out immediately
 * and bounced to the home page with a flash message.
 */
class RejectBannedUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBanned()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('home')->with('error', 'This account has been suspended. Contact support if you believe this is a mistake.');
        }

        return $next($request);
    }
}
