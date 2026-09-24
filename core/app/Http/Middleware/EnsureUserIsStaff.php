<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff gate for the admin panel (moderator+). Guests are bounced to the
 * login; authenticated non-staff get a 403.
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        abort_unless($user->isModerator(), 403);

        return $next($request);
    }
}
