<?php

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest Test Bootstrapping
|--------------------------------------------------------------------------
|
| Pest v3 bootstraps with `pest()->extends(TestCase::class)` (the v2
| `uses()` helper was removed). All tests run on in-memory SQLite with
| the same `database` queue/session drivers production uses (cPanel).
|
*/

pest()->extends(TestCase::class)->in('Feature', 'Unit', 'Arch');

/**
 * BH-R9-04 (v1.7.8) — the suite must never leave testing-flavoured
 * bootstrap caches behind.
 *
 * Several locks genuinely exercise the update pipeline in-place
 * (ReleaseHygieneTest D3, DeployParityAcceptanceTest, UpdaterFailureRecordTest)
 * by calling `pv:update --no-extract`. In production that pipeline
 * LEGITIMATELY ends with config:cache/route:cache; under phpunit the same
 * calls write `bootstrap/cache/*.php` with the TESTING environment baked
 * in — sqlite :memory:, array mail, testing view paths. The v1.7.7 release
 * suite left exactly that file on disk, and the next `php artisan serve`
 * or tinker boot read it: the whole dev app (frames, prompts, everything
 * DB-backed) silently pointed at an empty in-memory database until someone
 * ran config:clear. This guardian removes those artifacts after every
 * test; the release zip already excludes bootstrap/cache (the v1.4.4
 * incident), so only the local checkout is at stake.
 */
function removeTestingBootstrapCaches(): void
{
    foreach (['config.php', 'routes-v7.php', 'services.php', 'packages.php', 'events.php'] as $file) {
        $path = base_path('bootstrap/cache/'.$file);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}

afterEach(function () {
    removeTestingBootstrapCaches();
});

// T6 (v1.5.0): the tool-registry baseline lives in TestCase::setUp() —
// a global beforeEach() in this file registers under Pest.php's filename
// and never fires for actual test files (Pest v3 behavior).

/**
 * Assert a rendered response contains no Blade escape artifacts. A Blade
 * ECHO leak renders as `{{ $...` in served HTML (the v1.4.1 byline leak).
 * Prompt fill-in variables like `{{product_details}}` are legitimate page
 * CONTENT (no `$`, no Blade spacing) and must not trip this guard.
 */
function assertNoBladeLeak(TestResponse $response): void
{
    $body = $response->getContent() ?? '';

    $echoLeak = preg_match('/\{\{\s*\$/', $body, $m, PREG_OFFSET_CAPTURE) === 1;
    $sample = $echoLeak
        ? substr($body, max(0, $m[0][1] - 80), 200)
        : '';

    expect($echoLeak)
        ->toBeFalse('Rendered page contains a Blade echo leak ("{{ $") — an un-echoed placeholder reached the user. Sample: '.$sample)
        ->and(str_contains($body, "\u{2192}"))
        ->toBeFalse('Rendered page contains a raw U+2192 arrow — use &rarr; in views.');
}
