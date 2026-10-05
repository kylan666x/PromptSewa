<?php

use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\ForgetPerRequestState;
use App\Http\Middleware\RecordDailyVisit;
use App\Http\Middleware\RejectBannedUsers;
use App\Http\Middleware\ResolveImpersonation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
        ]);

        // M3 (v1.6.0): the eSewa webhook is signature-verified instead of
        // CSRF-protected (server-to-server, no session cookie). Keep this
        // exemption narrow — signature verification IS the guard here.
        $middleware->validateCsrfTokens(except: [
            'payments/esewa/webhook',
        ]);

        // A4: banned accounts are locked out of everything authenticated.
        // Appended to the web group (after StartSession so the logout can
        // invalidate the session; guests never reach the user check).
        $middleware->appendToGroup('web', RejectBannedUsers::class);

        // T3 (v1.5.0): effective-user resolution for admin impersonation —
        // validates the session's impersonator_id after StartSession,
        // before auth-dependent routes run.
        $middleware->appendToGroup('web', ResolveImpersonation::class);

        // H4 (v1.7.4): per-request viewer state (bookmarked ids) must never
        // outlive its request — a long-lived worker or the test client would
        // otherwise serve the previous viewer saved state.
        $middleware->appendToGroup('web', ForgetPerRequestState::class);

        // S3 (v1.8.0): daily-visit engagement reward. One row per user per
        // day (the ledger's UNIQUE key dedupes); the service owns the
        // kill-switch, the amount and the daily cap — this only decides
        // when an authenticated GET is a candidate visit.
        $middleware->appendToGroup('web', RecordDailyVisit::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // cPanel hardening: never leak stack traces to visitors. The cron
        // schedule emails failures to this address instead (see routes/console.php).
        if (app()->environment('production')) {
            ini_set('display_errors', '0');
        }
    })->create();
