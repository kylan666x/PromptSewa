<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| PromptSewa Schedule (cPanel Cron)
|--------------------------------------------------------------------------
|
| cPanel shared hosting has no daemons: no Supervisor, no Redis workers.
| The single cron entry (`* * * * * php artisan schedule:run`) drives
| everything — including the database queue worker via `queue:work --stop-when-empty`
| below, so queued jobs drain every minute without a resident process (PRD §6).
|
*/

// 1. Drain the database queue. --stop-when-empty makes the worker exit
//    after finishing pending jobs (cron-safe, no orphaned PHP processes
//    holding memory on a 512 MB shared host). 60s timeout = cron ceiling.
Schedule::command('queue:work database --stop-when-empty --max-jobs=100 --tries=3 --timeout=60')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('promptsewa.alert_email'))
    ->description('Drain database queue (cPanel cron)');

// 2. Delete stale cache-table rows (database cache driver housekeeping).
Schedule::command('cache:prune-stale-tags')->hourly();

// 3. Remove orphaned batch/job records (database queue housekeeping).
Schedule::command('queue:prune-batches --hours=48')->dailyAt('03:10');

// 4. Recalculate leaderboards from XP (PRD §3.4) — cheap on indexed tables,
//    cached afterwards so visitors never trigger the query themselves.
Schedule::command('promptsewa:recompute-leaderboards')->dailyAt('04:00');
