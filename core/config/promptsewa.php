<?php

return [

    /*
    |----------------------------------------------------------------------
    | PromptSewa Application Settings
    |----------------------------------------------------------------------
    |
    | Read via config() (NOT env()) so values survive `config:cache`,
    | which docs/DEPLOYMENT.md instructs to run on production.
    |
    */

    // Cron/scheduler failures are emailed here (see routes/console.php).
    'alert_email' => env('DEPLOY_ALERT_EMAIL'),

    // XP awards (PRD §3.4) — single source of truth for gamification jobs.
    'xp' => [
        'prompt_published' => 50,
        'prompt_forked' => 10,
        'review_written' => 5,
        'sale' => 25,
    ],
];
