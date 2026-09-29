<?php

use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\RejectBannedUsers;
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

        // A4: banned accounts are locked out of everything authenticated.
        // Appended to the web group (after StartSession so the logout can
        // invalidate the session; guests never reach the user check).
        $middleware->appendToGroup('web', RejectBannedUsers::class);

        // T3 (v1.5.0): effective-user resolution for admin impersonation —
        // validates the session's impersonator_id after StartSession,
        // before auth-dependent routes run.
        $middleware->appendToGroup('web', \App\Http\Middleware\ResolveImpersonation::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // cPanel hardening: never leak stack traces to visitors. The cron
        // schedule emails failures to this address instead (see routes/console.php).
        if (app()->environment('production')) {
            ini_set('display_errors', '0');
        }
    })->create();
