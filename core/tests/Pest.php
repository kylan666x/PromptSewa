<?php

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

pest()->extends(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Domain Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBePaisa', fn (int $expected) => $this->toBe($expected)
    ->and($expected)->toBeInt());
