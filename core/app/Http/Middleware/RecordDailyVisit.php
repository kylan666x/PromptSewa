<?php

namespace App\Http\Middleware;

use App\Services\SikkaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * S3 (v1.8.0) — daily-visit engagement reward.
 *
 * One reward per (user, calendar day), fired from any authenticated GET:
 * the ledger's UNIQUE idempotency key (`engage:{user}:daily_visit:{date}`)
 * makes the first page view of the day the credited one and every later
 * view a no-op. The service owns every bound (kill-switch, amount, daily
 * cap); this middleware only decides WHEN a visit is candidate — a
 * logged-in GET, never a POST and never a guest.
 */
class RecordDailyVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $request->isMethod('GET')) {
            app(SikkaService::class)->rewardEngagement($user, SikkaService::ENGAGEMENT_DAILY_VISIT);
        }

        return $next($request);
    }
}
