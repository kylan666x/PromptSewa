<?php

/**
 * T1 (v1.7.3) — Bot Challenge (CAPTCHA) configuration.
 *
 * The provider and credentials live in SETTINGS (admin-editable at
 * runtime, secret encrypted at rest via SettingsService::SECRET_KEYS) —
 * NOT here. This config file only holds defaults for tests/console and
 * the per-driver wire format.
 */

return [

    // null | turnstile | recaptcha_v3 (settings key captcha_provider wins).
    'provider' => env('CAPTCHA_PROVIDER'),

    'site_key' => env('CAPTCHA_SITE_KEY'),
    'secret' => env('CAPTCHA_SECRET'),

    // recaptcha_v3 only: minimum score to accept (0.0–1.0).
    'min_score' => env('CAPTCHA_MIN_SCORE', 0.5),

    // false = fail-closed (transport error → reject). The settings key
    // captcha_fail_open wins over this default.
    'fail_open' => env('CAPTCHA_FAIL_OPEN', false),

    // Verification endpoints (server-side, never exposed to the client).
    'endpoints' => [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    // Script sources for the widget (rendered only when the provider is active).
    'scripts' => [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api.js?render=',
    ],

];
