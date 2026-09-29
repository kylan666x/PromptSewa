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
