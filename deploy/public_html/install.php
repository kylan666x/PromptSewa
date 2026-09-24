<?php

/*
|--------------------------------------------------------------------------
| PromptSewa — Web Installer (deploy/public_html/install.php)
|--------------------------------------------------------------------------
|
| One-file, zero-dependency installer for shared cPanel / LiteSpeed hosts.
| Upload this file into public_html/ next to index.php, open it in the
| browser, and it will:
|
|   1. Run a preflight check (PHP version, extensions, writable paths)
|   2. Test the MySQL credentials you provide (cPanel → MySQL® Databases)
|   3. Write core/.env (with a freshly generated APP_KEY)
|   4. Run migrations, storage:link and config/route/view caches
|   5. Create your admin account
|   6. Copy compiled assets (public/build) into public_html so they are
|      served straight from the docroot (no rewrite tricks required)
|   7. Write a random .update-token for update.php, then lock itself
|
| SECURITY: the installer refuses to run once core/.env contains an
| APP_KEY (i.e. the app is installed). Delete this file after installing.
|
| No Composer, no SSH, no Node.js — pure PHP 8.2+. Works on Apache AND
| LiteSpeed (no webserver-specific APIs are used).
|
*/

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1'); // installer surface only — production .env turns this off

session_start();

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

const MIN_PHP = '8.2.0';

/**
 * Laravel's required extensions + the ones PromptSewa needs in
 * production (pdo_mysql, zip, bcmath, gd, intl, exif).
 */
const REQUIRED_EXTENSIONS = [
    'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring',
    'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml',
    'zip', 'bcmath', 'gd', 'intl', 'exif',
];

/**
 * Where the Laravel app lives. Documented layout: core/ sits next to
 * public_html/ (siblings in /home/CPANELUSER/). We also accept the
 * "core inside public_html" layout as a fallback.
 */
function detect_core_path(): ?string
{
    $candidates = [
        dirname(__DIR__).DIRECTORY_SEPARATOR.'core', // /home/USER/core (documented)
        __DIR__.DIRECTORY_SEPARATOR.'core',          // /home/USER/public_html/core
    ];

    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/* -------------------------------------------------------------------------
 * Tiny render helpers (kept dependency-free and framework-less)
 * ---------------------------------------------------------------------- */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_head(string $title): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta name="robots" content="noindex,nofollow">'
        .'<title>'.e($title).' — PromptSewa Installer</title>'
        .'<style>
            :root{color-scheme:dark}
            *{box-sizing:border-box}
            body{margin:0;font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0c0a09;color:#e7e5e4}
            main{max-width:820px;margin:0 auto;padding:48px 24px 96px}
            h1{font-size:1.9rem;margin:0 0 4px;color:#fafaf9}
            h1 .pv{display:inline-block;background:#f59e0b;color:#0c0a09;border-radius:12px;padding:2px 12px;margin-right:10px}
            h2{font-size:1.15rem;color:#fafaf9;margin:36px 0 10px;border-bottom:1px solid #292524;padding-bottom:8px}
            p.sub{color:#a8a29e;margin-top:0}
            .card{background:#1c1917;border:1px solid #292524;border-radius:14px;padding:20px 22px;margin:14px 0}
            table{border-collapse:collapse;width:100%;font-size:.95rem}
            td,th{padding:7px 10px;border-bottom:1px solid #292524;text-align:left;vertical-align:top}
            th{color:#a8a29e;font-weight:600;width:34%}
            .ok{color:#4ade80;font-weight:600}
            .bad{color:#f87171;font-weight:600}
            .warn{color:#fbbf24;font-weight:600}
            code,.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.9em;background:#292524;border-radius:6px;padding:2px 6px}
            pre{background:#1c1917;border:1px solid #292524;border-radius:10px;padding:14px;overflow:auto;font-size:.85rem}
            label{display:block;margin:14px 0 4px;color:#d6d3d1;font-weight:600;font-size:.92rem}
            input[type=text],input[type=password],input[type=email],select{width:100%;padding:10px 12px;border-radius:9px;border:1px solid #44403c;background:#0c0a09;color:#fafaf9;font-size:.95rem}
            input:focus,select:focus{outline:2px solid #f59e0b;border-color:#f59e0b}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}
            @media(max-width:640px){.grid{grid-template-columns:1fr}}
            button,.btn{display:inline-block;background:#f59e0b;color:#0c0a09;border:0;border-radius:10px;padding:12px 26px;font-weight:800;font-size:1rem;cursor:pointer;text-decoration:none}
            button:hover,.btn:hover{background:#fbbf24}
            .btn-danger{background:#7f1d1d;color:#fecaca}
            .btn-danger:hover{background:#991b1b}
            .hint{font-size:.85rem;color:#a8a29e;margin:4px 0 0}
            .logo{color:#f59e0b;font-weight:800}
            .steps{color:#a8a29e;font-size:.9rem}
            .steps b{color:#e7e5e4}
            ul.log{list-style:none;padding:0;margin:0}
            ul.log li{padding:5px 0;border-bottom:1px dashed #292524;font-size:.92rem}
         </style></head><body><main>';
}

function render_foot(): void
{
    echo '</main></body></html>';
}

function check_row(string $label, bool $ok, string $detail): void
{
    echo '<tr><th>'.e($label).'</th><td><span class="'.($ok ? 'ok">✔ PASS' : 'bad">✘ FAIL').'</span> &nbsp;'.e($detail).'</td></tr>';
}

/* -------------------------------------------------------------------------
 * Step 0: lock screen — refuse to run on an installed app
 * ---------------------------------------------------------------------- */

function installed_lock_path(string $core): string
{
    return $core.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'installed.lock';
}

function app_is_installed(string $core): bool
{
    if (is_file(installed_lock_path($core))) {
        return true;
    }

    // Belt & suspenders: an .env containing a real APP_KEY means installed.
    $env = $core.DIRECTORY_SEPARATOR.'.env';
    if (is_file($env)) {
        $contents = (string) file_get_contents($env);

        return (bool) preg_match('/^APP_KEY=base64:.+$/m', $contents);
    }

    return false;
}

/* -------------------------------------------------------------------------
 * .env template
 * ---------------------------------------------------------------------- */

function env_template(array $in, string $key): string
{
    $db = $in['driver'] === 'sqlite'
        ? "DB_CONNECTION=sqlite\n#DB_HOST=localhost\n#DB_PORT=3306\n#DB_DATABASE=\n#DB_USERNAME=\n#DB_PASSWORD="
        : "DB_CONNECTION=mysql\nDB_HOST={$in['db_host']}\nDB_PORT=3306\nDB_DATABASE={$in['db_name']}\nDB_USERNAME={$in['db_user']}\nDB_PASSWORD={$in['db_pass']}";

    $mail = $in['mail_host'] !== ''
        ? "MAIL_MAILER=smtp\nMAIL_HOST={$in['mail_host']}\nMAIL_PORT={$in['mail_port']}\nMAIL_USERNAME={$in['mail_user']}\nMAIL_PASSWORD={$in['mail_pass']}"
        : "MAIL_MAILER=log\nMAIL_HOST=127.0.0.1\nMAIL_PORT=2525\nMAIL_USERNAME=null\nMAIL_PASSWORD=null";

    return <<<ENV
# =============================================================
# PromptSewa — generated by install.php on {$in['generated']}
# Edit via cPanel File Manager if values change.
# =============================================================

APP_NAME=PromptSewa
APP_ENV=production
APP_KEY={$key}
APP_DEBUG=false
APP_URL={$in['app_url']}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

{$db}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=database

SCOUT_DRIVER=database
SCOUT_QUEUE=false

CACHE_STORE=database
CACHE_PREFIX=promptvellum

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

{$mail}
MAIL_FROM_ADDRESS={$in['mail_from']}
MAIL_FROM_NAME="\${APP_NAME}"

# Scheduler/cron failure alerts (configure in cPanel Cron Jobs)
DEPLOY_ALERT_EMAIL={$in['alert_email']}

# Marketplace / Finance
PLATFORM_FEE_PERCENT=20
CURRENCY=NPR

# eSewa payment gateway (configure later in Admin → Settings)
ESEWA_MERCHANT_CODE=
ESEWA_SECRET_KEY=
ESEWA_BASE_URL=https://rc.esewa.com.np

VITE_APP_NAME="\${APP_NAME}"
ENV;
}

function env_value(string $contents, string $key): ?string
{
    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $m)) {
        return trim($m[1], "\"'");
    }

    return null;
}

/* -------------------------------------------------------------------------
 * Run the install (POST handler)
 * ---------------------------------------------------------------------- */

/** @return string[] log lines; throws RuntimeException on fatal errors */
function run_install(string $core, array $in): array
{
    $log = [];

    if (!empty($in['sqlite_note'])) {
        $log[] = ['warn', 'SQLite selected — evaluation/testing only. Production installs must use MySQL.'];
    }

    // 1) Verify DB connection BEFORE touching anything.
    if ($in['driver'] === 'mysql') {
        try {
            $pdo = new PDO(
                "mysql:host={$in['db_host']};port=3306;dbname={$in['db_name']}",
                $in['db_user'],
                $in['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10],
            );
            $pdo = null;
            $log[] = ['ok', "MySQL connection OK ({$in['db_user']}@{$in['db_host']}/{$in['db_name']})"];
        } catch (PDOException $e) {
            throw new RuntimeException(
                'MySQL connection failed: '.$e->getMessage()
                .' — check the database name/user you created in cPanel → MySQL® Databases (names are usually prefixed, e.g. cpaneluser_promptvellum).'
            );
        }
    } else {
        $sqlite = $core.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        if (!is_file($sqlite) && !touch($sqlite)) {
            throw new RuntimeException('Could not create the SQLite file at database/database.sqlite.');
        }
        $log[] = ['ok', 'SQLite database file ready (fine for testing — use MySQL in production).'];
    }

    // 2) Backup any existing .env, then write the new one.
    $envPath = $core.DIRECTORY_SEPARATOR.'.env';
    if (is_file($envPath)) {
        $backup = $envPath.'.backup-'.date('Ymd-His');
        if (!@rename($envPath, $backup)) {
            throw new RuntimeException('Could not back up the existing .env — make sure the core/ folder is writable.');
        }
        $log[] = ['ok', 'Existing .env backed up to '.basename($backup)];
    }

    $appKey = 'base64:'.base64_encode(random_bytes(32));
    if (!@file_put_contents($envPath, env_template($in, $appKey))) {
        throw new RuntimeException('Could not write core/.env — check folder permissions in cPanel File Manager.');
    }
    @chmod($envPath, 0600); // secrets — owner-only where the FS allows it
    $log[] = ['ok', '.env written with a freshly generated APP_KEY'];

    // 3) Boot Laravel from core/ and drive Artisan in-process.
    //    (No exec/shell needed — those are disabled on most shared hosts.)
    $autoload = $core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException(
            'vendor/ not found inside core/ — you must upload the prepared release zip '
            .'(built by deploy/build-release.sh or downloaded from GitHub Actions), which includes vendor/.'
        );
    }

    require $autoload;
    $app = require $core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $log[] = ['ok', 'Laravel booted (App '.($app->version() ?? '').')'];

    $artisan = static function (string $command, array $params = []) use ($app): string {
        $exit = $app->make(Illuminate\Contracts\Console\Kernel::class)->call($command, $params);

        return trim($app->make(Illuminate\Contracts\Console\Kernel::class)->output());
    };

    // 4) Migrations — if this fails we ROLL BACK the .env we just wrote so
    //    the installer stays unlocked and the user can fix the DB and retry.
    //    (Previously a mid-migration crash left a valid .env behind, which
    //    locked the installer with no admin account and no seed data.)
    try {
        $output = $artisan('migrate', ['--force' => true]);
    } catch (Throwable $e) {
        @unlink($envPath);
        throw new RuntimeException(
            'Migration failed and the install was ROLLED BACK (you can retry safely): '.$e->getMessage()
        );
    }
    $log[] = ['ok', 'Database migrations executed'];
    $log[] = ['info', 'migrate: '.($output !== '' ? $output : '(nothing to migrate)')];

    // 5) Admin account (first user — role: admin)
    $user = App\Models\User::create([
        'name' => $in['admin_name'],
        'email' => $in['admin_email'],
        'password' => $in['admin_pass'], // hashed automatically by the model cast
        'role' => App\Models\User::ROLE_ADMIN,
        'email_verified_at' => now(),
    ]);
    $log[] = ['ok', "Admin account created: {$user->email}"];

    // 5b) Seed demo marketplace content (categories, demo prompts, the
    //     JustShipItAI flagship profile and the tool-logo registry).
    //     All seeders are idempotent — safe to re-run on updates.
    if (!empty($in['seed_demo'])) {
        try {
            $output = $artisan('db:seed', ['--force' => true]);
            $log[] = ['ok', 'Demo content seeded (categories, prompts, JustShipItAI profile)'];
            $log[] = ['info', 'seed: '.($output !== '' ? $output : '(no output)')];
        } catch (Throwable $e) {
            $log[] = ['warn', 'Seeding failed (site still works, but no demo content): '.$e->getMessage()];
        }
    } else {
        $log[] = ['warn', 'Demo content seeding skipped (empty category dropdown until you add categories in Admin).'];
    }

    // 6) Storage link (best effort — .htaccess also maps /storage directly)
    try {
        $artisan('storage:link');
        $log[] = ['ok', 'storage:link created'];
    } catch (Throwable $e) {
        $log[] = ['warn', 'storage:link failed ('.$e->getMessage().') — /storage/ is served by .htaccess rewrite instead.'];
    }

    // 7) Optimized caches (skip in "debug mode" installs)
    if (empty($in['skip_caches'])) {
        foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
            try {
                $artisan($cmd);
                $log[] = ['ok', $cmd.' ✓'];
            } catch (Throwable $e) {
                $log[] = ['warn', $cmd.' failed: '.$e->getMessage()];
            }
        }
    } else {
        $log[] = ['warn', 'Caches skipped (debug mode was selected).'];
    }

    // 8) Copy compiled assets into public_html so they always serve,
    //    even on hosts that block rewrites above the docroot.
    $copied = sync_build_assets($core, __DIR__);
    $log[] = $copied
        ? ['ok', 'Compiled assets (public/build) copied into public_html/']
        : ['warn', 'No public/build found — upload the release zip with built assets, then run update.php.'];

    // 9) Update token for update.php (stored as a DOTFILE — .htaccess blocks dotfiles)
    $token = bin2hex(random_bytes(16));
    file_put_contents(__DIR__.'/.update-token', $token);
    @chmod(__DIR__.'/.update-token', 0600);
    $log[] = ['ok', 'Update token written to public_html/.update-token (for update.php)'];

    // 10) Install lock
    file_put_contents(installed_lock_path($core), json_encode([
        'installed_at' => date('c'),
        'admin' => $in['admin_email'],
        'app_url' => $in['app_url'],
    ], JSON_PRETTY_PRINT));
    $log[] = ['ok', 'Installer locked (storage/app/installed.lock)'];

    return $log;
}

/** Copy core/public/build → public_html/build; returns true on success. */
function sync_build_assets(string $core, string $docroot): bool
{
    $src = $core.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build';
    $dst = $docroot.DIRECTORY_SEPARATOR.'build';

    if (!is_dir($src)) {
        return false;
    }

    // Fresh copy: stale hashed files only confuse.
    if (is_dir($dst)) {
        recurse_rmdir($dst);
    }

    recurse_copy($src, $dst);

    foreach (['favicon.ico', 'robots.txt'] as $file) {
        if (is_file($core.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$file)) {
            @copy($core.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.$file, $docroot.DIRECTORY_SEPARATOR.$file);
        }
    }

    return true;
}

function recurse_copy(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        @mkdir($dst, 0755, true);
    }
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $s = $src.DIRECTORY_SEPARATOR.$item;
        $d = $dst.DIRECTORY_SEPARATOR.$item;
        if (is_dir($s)) {
            recurse_copy($s, $d);
        } else {
            @copy($s, $d);
        }
    }
}

function recurse_rmdir(string $dir): void
{
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        if (is_dir($path)) {
            recurse_rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/* =========================================================================
 * HTTP flow
 * ====================================================================== */

$core = detect_core_path();

render_head('Install');

echo '<h1><span class="pv">PV</span>PromptSewa Installer</h1>';
echo '<p class="sub">Zero-dependency web installer for cPanel / LiteSpeed shared hosting.</p>';

if ($core === null) {
    echo '<div class="card"><span class="bad">✘ core/ folder not found.</span><p>Upload the PromptSewa release so that the <code>core</code> folder sits at <code>/home/YOURUSER/core/</code> (next to <code>public_html/</code>), then reload this page. Expected: <code>'.e(dirname(__DIR__).DIRECTORY_SEPARATOR.'core').'</code></p></div>';
    render_foot();
    exit;
}

if (app_is_installed($core)) {
    echo '<div class="card">'
        .'<h2 style="margin-top:0;border:0">✔ PromptSewa is already installed</h2>'
        .'<p>This installer is <b>locked</b> because <code>core/.env</code> already contains an application key.</p>'
        .'<p class="steps">• To apply a new version: use <a class="btn" style="padding:8px 18px" href="update.php">update.php</a> (after uploading the new release zip)<br>'
        .'• To re-install from scratch: delete <code>core/.env</code> and <code>core/storage/app/installed.lock</code> via cPanel File Manager first.</p>'
        .'</div>';
    render_foot();
    exit;
}

/* -------- Step 2: run (POST) -------- */

$csrfOk = isset($_POST['csrf'], $_SESSION['install_csrf']) && hash_equals($_SESSION['install_csrf'], (string) $_POST['csrf']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$csrfOk) {
        echo '<div class="card"><span class="bad">✘ Invalid session token — reload and try again.</span></div>';
        render_foot();
        exit;
    }

    if ($_POST['action'] === 'secure') {
        $removed = @unlink(__FILE__);
        echo '<div class="card"><h2 style="margin-top:0;border:0">Installation complete 🎉</h2>'
            .($removed
                ? '<p><span class="ok">install.php has deleted itself.</span></p>'
                : '<p><span class="warn">Could not self-delete — please delete <code>install.php</code> manually via cPanel File Manager now.</span></p>')
            .'<p class="steps">Next: open <a href="/">your site</a> and log in with the admin account you created. Then add the one-minute cron job (see below).</p>'
            .'</div>';
        render_foot();
        exit;
    }

    // action === 'install'
    $in = [
        'driver' => ($_POST['driver'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql',
        'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')) ?: 'localhost',
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? '')),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'app_url' => rtrim(trim((string) ($_POST['app_url'] ?? '')), '/'),
        'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
        'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
        'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
        'alert_email' => trim((string) ($_POST['alert_email'] ?? '')),
        'mail_host' => trim((string) ($_POST['mail_host'] ?? '')),
        'mail_port' => trim((string) ($_POST['mail_port'] ?? '587')),
        'mail_user' => trim((string) ($_POST['mail_user'] ?? '')),
        'mail_pass' => (string) ($_POST['mail_pass'] ?? ''),
        'mail_from' => trim((string) ($_POST['mail_from'] ?? '')),
        'skip_caches' => !empty($_POST['debug_mode']),
        'seed_demo' => !empty($_POST['seed_demo']),
        'generated' => date('c'),
    ];

    $errors = [];
    if ($in['driver'] === 'mysql' && ($in['db_name'] === '' || $in['db_user'] === '')) {
        $errors[] = 'MySQL database name and username are required (cPanel names look like cpaneluser_pv).';
    }
    if ($in['app_url'] === '') {
        $errors[] = 'Site URL is required.';
    }
    if ($in['admin_name'] === '' || !filter_var($in['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin name and email are required.';
    }
    if (strlen($in['admin_pass']) < 10) {
        $errors[] = 'Admin password must be at least 10 characters.';
    }
    if ($in['driver'] === 'sqlite') {
        $in['sqlite_note'] = true; // logged below — evaluation/testing only
    }

    if ($errors !== []) {
        echo '<div class="card">';
        foreach ($errors as $error) {
            echo '<p><span class="bad">✘</span> '.e($error).'</p>';
        }
        echo '<p><a href="install.php">← Go back and fix the form</a></p></div>';
        render_foot();
        exit;
    }

    echo '<h2>Installing…</h2><div class="card"><ul class="log">';
    @set_time_limit(300);

    try {
        $log = run_install($core, $in);
        $failed = false;
    } catch (Throwable $e) {
        $log = $log ?? [];
        $log[] = ['bad', 'FATAL: '.$e->getMessage()];
        $failed = true;
    }

    foreach ($log as [$level, $line]) {
        echo '<li><span class="'.e($level).'">'.e($line).'</span></li>';
    }
    echo '</ul></div>';

    if (!$failed) {
        $cronPhp = '/usr/local/bin/php'; // verify with `which php` in cPanel Terminal, or use MultiPHP Manager's path
        echo '<div class="card"><h2 style="margin-top:0;border:0">Almost done — one cron job</h2>'
            .'<p>cPanel → <b>Cron Jobs</b> → Once Per Minute:</p>'
            ."<pre>cd ".e($core)." && {$cronPhp} artisan schedule:run >> /dev/null 2>&1</pre>"
            .'<p class="hint">If <code>/usr/local/bin/php</code> is not your PHP binary, run <code>which php</code> in cPanel Terminal, or check MultiPHP Manager. This single cron drives queues, the scheduler and nightly housekeeping — no Supervisor needed.</p></div>';

        echo '<div class="card"><h2 style="margin-top:0;border:0">Secure the installer</h2>'
            .'<p>The installer locked itself, but best practice is to remove it entirely.</p>'
            .'<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.e($_SESSION['install_csrf']).'"><input type="hidden" name="action" value="secure">'
            .'<button type="submit">Delete install.php now</button></form> '
            .'<a class="btn" style="margin-left:8px" href="update.php" title="For future updates">Open update.php</a></div>';

        // Do not leak the token on-screen; it lives in .update-token (dotfile, blocked by .htaccess).
    }

    render_foot();
    exit;
}

/* -------- Step 1: preflight + form -------- */

$_SESSION['install_csrf'] = bin2hex(random_bytes(16));

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$guessedUrl = $scheme.'://'.$host;

echo '<h2>Step 1 — Server requirements</h2><div class="card"><table>';

$phpOk = version_compare(PHP_VERSION, MIN_PHP, '>=');
check_row('PHP version', $phpOk, PHP_VERSION.($phpOk ? ' (≥ '.MIN_PHP.' required)' : ' — upgrade via cPanel → MultiPHP Manager'));

$missing = [];
foreach (REQUIRED_EXTENSIONS as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}
check_row('PHP extensions', $missing === [], $missing === [] ? implode(', ', REQUIRED_EXTENSIONS) : 'missing: '.implode(', ', $missing));

check_row('Laravel app found', is_file($core.DIRECTORY_SEPARATOR.'artisan'), e($core));
check_row('vendor/ uploaded', is_file($core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php'),
    is_file($core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php') ? 'composer dependencies present' : 'upload the prepared release zip (includes vendor/)');

$writableDirs = [$core, $core.DIRECTORY_SEPARATOR.'storage', $core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', $core.DIRECTORY_SEPARATOR.'database', __DIR__];
$notWritable = array_values(array_filter($writableDirs, static fn ($d) => !is_writable($d)));
check_row('Writable paths', $notWritable === [], $notWritable === [] ? 'core/, storage/, bootstrap/cache/, database/, public_html/' : 'not writable: '.implode(', ', array_map('basename', $notWritable)));

echo '</table>';
if ($phpOk && $missing === [] && is_file($core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php') && $notWritable === []) {
    echo '<p><span class="ok">All checks passed — continue below.</span></p>';
} else {
    echo '<p><span class="bad">Fix the failing checks above (cPanel → MultiPHP Manager / Select PHP Version for PHP &amp; extensions), then reload this page.</span></p>';
    render_foot();
    exit;
}
echo '</div>';

echo '<h2>Step 2 — Database</h2><div class="card">'
    .'<p class="hint">Create these first in cPanel → <b>MySQL® Databases</b>: a database (e.g. <code>cpaneluser_promptvellum</code>), a user (<code>cpaneluser_pv</code>), and grant the user <b>ALL PRIVILEGES</b> on the database.</p>'
    .'<form method="post"><input type="hidden" name="csrf" value="'.e($_SESSION['install_csrf']).'"><input type="hidden" name="action" value="install">'
    .'<label>Database driver</label><select name="driver"><option value="mysql" selected>MySQL / MariaDB (required for production)</option><option value="sqlite">SQLite — evaluation/testing only</option></select>'
    .'<div class="grid"><div><label>DB host</label><input type="text" name="db_host" value="localhost" required><p class="hint">cPanel MySQL is nearly always <code>localhost</code>.</p></div>'
    .'<div><label>Database name</label><input type="text" name="db_name" placeholder="cpaneluser_promptvellum" required></div>'
    .'<div><label>DB username</label><input type="text" name="db_user" placeholder="cpaneluser_pv" required></div>'
    .'<div><label>DB password</label><input type="password" name="db_pass" autocomplete="new-password"></div></div>';

echo '</div>';

echo '<h2>Step 3 — Site &amp; admin account</h2><div class="card">'
    .'<div class="grid">'
    .'<div><label>Site URL</label><input type="text" name="app_url" value="'.e($guessedUrl).'" required></div>'
    .'<div><label>Admin name</label><input type="text" name="admin_name" placeholder="Site Owner" required></div>'
    .'<div><label>Admin email</label><input type="email" name="admin_email" placeholder="you@yourdomain.com" required></div>'
    .'<div><label>Admin password (min 10 chars)</label><input type="password" name="admin_pass" minlength="10" required></div>'
    .'<div><label>Cron alert email (optional)</label><input type="email" name="alert_email" placeholder="alerts@yourdomain.com"></div>'
    .'<div><label>Debug mode (skip caches — testing only)</label><select name="debug_mode"><option value="">No — production mode (recommended)</option><option value="1">Yes — skip optimization caches</option></select></div>'
    .'</div>'
    .'<label style="display:flex;align-items:center;gap:8px;margin-top:16px"><input type="checkbox" name="seed_demo" value="1" checked> Seed demo marketplace content (categories, demo prompts, JustShipItAI profile)</label>'
    .'<p class="hint">Recommended: keep this on so the Add Prompt form has categories to offer and the storefront is not empty. Seeders are idempotent and never overwrite existing data.</p>'
    .'<h2>Mail (optional — can be configured later in .env)</h2>'
    .'<div class="grid">'
    .'<div><label>SMTP host</label><input type="text" name="mail_host" placeholder="mail.yourdomain.com"></div>'
    .'<div><label>SMTP port</label><input type="text" name="mail_port" value="587"></div>'
    .'<div><label>SMTP username</label><input type="text" name="mail_user"></div>'
    .'<div><label>SMTP password</label><input type="password" name="mail_pass" autocomplete="new-password"></div>'
    .'<div><label>From address</label><input type="email" name="mail_from" placeholder="no-reply@yourdomain.com"></div>'
    .'</div>'
    .'</div>';

echo '<div class="card">'
    .'<p class="hint">On submit the installer will: test the DB → write <code>core/.env</code> (existing one is backed up) → run migrations → create the admin → seed demo content → set caches → copy assets → lock itself. If any step fails, the install is rolled back and you can retry.</p>'
    .'<button type="submit">🚀 Install PromptSewa</button></form></div>';

render_foot();
