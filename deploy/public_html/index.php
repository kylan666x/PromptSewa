<?php

/*
|--------------------------------------------------------------------------
| PromptSewa — public_html/index.php (cPanel front controller)
|--------------------------------------------------------------------------
|
| PRD §6: the Laravel app lives OUTSIDE the document root, at
| /home/CPANELUSER/core/ (a sibling of public_html/). This file is the
| ONLY PHP entry point exposed to the web; public_html/.htaccess routes
| every non-static request here, and it boots the hidden application.
|
| Layout auto-detection: the sibling /home/USER/core is used first; if
| you instead placed core inside public_html, that is picked up too.
|
*/

// /home/CPANELUSER/core  (fallback: /home/CPANELUSER/public_html/core)
$coreCandidates = [
    dirname(__DIR__).DIRECTORY_SEPARATOR.'core',
    __DIR__.DIRECTORY_SEPARATOR.'core',
];

foreach ($coreCandidates as $candidate) {
    if (is_file($candidate.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
        define('CORE_PATH', $candidate);
        break;
    }
}

if (! defined('CORE_PATH')) {
    http_response_code(503);
    exit("PromptSewa is not installed yet.<br>\nIf this is a fresh server, open <a href=\"install.php\">install.php</a> to install it.");
}

// A missing/unconfigured .env means the installer never ran — point the way.
$envFile = CORE_PATH.'/.env';
if (! is_file($envFile) || ! preg_match('/^APP_KEY=base64:.+$/m', (string) file_get_contents($envFile))) {
    http_response_code(503);
    exit("PromptSewa is not configured yet.<br>\nOpen <a href=\"install.php\">install.php</a> to install it.");
}

define('LARAVEL_START', microtime(true));

// Maintenance mode: Laravel writes this file during `php artisan down`,
// so honor it here exactly like the stock public/index.php would.
if (file_exists($maintenance = CORE_PATH.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require CORE_PATH.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once CORE_PATH.'/bootstrap/app.php';

$app->handleRequest(\Illuminate\Http\Request::capture());
