<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * H4 (v1.7.4) — drop per-request memoised viewer state.
 *
 * `bookmarked.ids` (App\Support\BookmarkedIds) is a container singleton so a
 * grid of cards costs ONE query per request. A container singleton normally
 * lives for the whole PHP process, which is fine on classic PHP-FPM — but a
 * long-lived worker (Octane, a queue worker) or the Laravel TEST client would
 * otherwise serve the PREVIOUS request's viewer state: the heart of prompt A
 * stays filled for a viewer who never saved it, and a fresh save is invisible
 * until the process restarts. That is precisely the class of bug H4 exists to
 * kill, so the memo is dropped on the way in, every request.
 *
 * The state must depend on the SESSION, not on who logged in most recently —
 * forgetting per request makes "one query per request" true and keeps the
 * read-after-write behaviour the tests rely on.
 */
class ForgetPerRequestState
{
    public function handle(Request $request, Closure $next)
    {
        app()->forgetInstance('bookmarked.ids');

        return $next($request);
    }
}