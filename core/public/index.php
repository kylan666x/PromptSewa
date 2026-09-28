<?php

/*
|--------------------------------------------------------------------------
| PromptSewa — Laravel front controller (core/public/index.php)
|--------------------------------------------------------------------------
|
| This is the LOCAL / development entry point used by `php artisan serve`
| and any webserver whose docroot is core/public. On the cPanel host the
| docroot is public_html, whose own front controller
| (deploy/public_html/index.php) boots THIS application from a sibling
| directory — do not copy that variant here.
|
*/

define('LARAVEL_START', microtime(true));

// Maintenance mode: Laravel writes this file during `php artisan down`,
// so honor it here exactly like the stock public/index.php would.
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(\Illuminate\Http\Request::capture());
