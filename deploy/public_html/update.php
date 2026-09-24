<?php

/*
|--------------------------------------------------------------------------
| PromptSewa — Web Updater (deploy/public_html/update.php)
|--------------------------------------------------------------------------
|
| One-file, zero-dependency updater. After uploading a new release
| (upload.zip extracted over /home/USER/ via cPanel File Manager, or a
| CI/CD FTP push), open this page once to:
|
|   1. Put the app into maintenance mode
|   2. Run pending database migrations
|   3. Rebuild config / route / view caches
|   4. Re-sync compiled assets (core/public/build → public_html/build)
|   5. Return the app online
|
| SECURITY: requires the random token stored in public_html/.update-token
| (a dotfile — web-invisible, blocked by .htaccess). Read it in cPanel →
| File Manager → "Show Hidden Files". The installer writes it for you.
| Delete this file after running an update.
|
*/

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1'); // updater surface only

session_start();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_head(string $title): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta name="robots" content="noindex,nofollow">'
        .'<title>'.e($title).' — PromptSewa Updater</title>'
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
            .ok{color:#4ade80;font-weight:600}
            .bad{color:#f87171;font-weight:600}
            .warn{color:#fbbf24;font-weight:600}
            code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.9em;background:#292524;border-radius:6px;padding:2px 6px}
            pre{background:#1c1917;border:1px solid #292524;border-radius:10px;padding:14px;overflow:auto;font-size:.85rem}
            label{display:block;margin:14px 0 4px;color:#d6d3d1;font-weight:600;font-size:.92rem}
            input[type=text],input[type=password]{width:100%;padding:10px 12px;border-radius:9px;border:1px solid #44403c;background:#0c0a09;color:#fafaf9;font-size:.95rem}
            button,.btn{display:inline-block;background:#f59e0b;color:#0c0a09;border:0;border-radius:10px;padding:12px 26px;font-weight:800;font-size:1rem;cursor:pointer;text-decoration:none}
            button:hover,.btn:hover{background:#fbbf24}
            .btn-danger{background:#7f1d1d;color:#fecaca}
            .btn-danger:hover{background:#991b1b}
            .hint{font-size:.85rem;color:#a8a29e;margin:4px 0 0}
            ul.log{list-style:none;padding:0;margin:0}
            ul.log li{padding:5px 0;border-bottom:1px dashed #292524;font-size:.92rem}
         </style></head><body><main>';
}

function render_foot(): void
{
    echo '</main></body></html>';
}

function detect_core_path(): ?string
{
    $candidates = [
        dirname(__DIR__).DIRECTORY_SEPARATOR.'core',
        __DIR__.DIRECTORY_SEPARATOR.'core',
    ];
    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            return $candidate;
        }
    }

    return null;
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

/**
 * Extract an uploaded release zip over the installation, then run the
 * normal update steps. Used by the admin-panel zip uploader.
 *
 * The zip must have the release layout: core/... (plus an optional
 * public_html/...). Docroot files land in __DIR__ (this script's folder
 * = the docroot); core/ lands one level up. Existing files are
 * overwritten in place — .env, .update-token and other dotfiles not
 * present in the zip are preserved.
 *
 * Update zips may ship core/ ONLY (no docroot files) — the docroot
 * files are already installed and never change between updates.
 *
 * @return string[] human-readable log lines
 */
function extract_release_zip(string $zipPath, string $core, string $docroot): array
{
    if (! class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ext-zip is not installed on this host — extract the zip via cPanel File Manager instead, then run this updater.');
    }

    $zip = new ZipArchive();
    $stat = $zip->open($zipPath);
    if ($stat !== true) {
        throw new RuntimeException("Could not open the uploaded zip (code {$stat}). The upload may be incomplete.");
    }

    // Safety: every entry must stay inside the release layout.
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name === '' || str_ends_with($name, '/')) {
            continue; // directory entries
        }
        if (str_contains($name, '..') || (! str_starts_with($name, 'core/') && ! str_starts_with($name, 'public_html/'))) {
            $zip->close();
            throw new RuntimeException("Rejected: zip entry '{$name}' is outside the release layout (expected only core/ and public_html/).");
        }
    }

    $log = [];
    $staging = dirname($docroot).DIRECTORY_SEPARATOR.'.update-staging-'.bin2hex(random_bytes(4));
    if (! @mkdir($staging, 0755, true)) {
        $zip->close();
        throw new RuntimeException('Could not create a staging directory beside the docroot — check folder permissions.');
    }

    try {
        if (! $zip->extractTo($staging)) {
            throw new RuntimeException('Zip extraction failed — check disk quota and permissions.');
        }
        $zip->close();
        $log[] = 'ok|Zip extracted to a staging directory';

        // Move core/ over the existing core (preserve live .env etc. — the
        // release ships .env.example only, and copy() overwrites binaries).
        $srcCore = $staging.DIRECTORY_SEPARATOR.'core';
        $srcPublic = $staging.DIRECTORY_SEPARATOR.'public_html';

        recurse_copy($srcCore, $core);
        $log[] = 'ok|core/ merged over the existing installation';

        if (is_dir($srcPublic)) {
            recurse_copy($srcPublic, $docroot);
            $log[] = 'ok|public_html/ merged into the docroot';
        } else {
            $log[] = 'ok|core-only update zip — docroot files untouched';
        }
    } finally {
        recurse_rmdir($staging);
    }

    return $log;
}

/** @return string[] */
function run_update(string $core): array
{
    $log = [];
    $envPath = $core.DIRECTORY_SEPARATOR.'.env';

    if (!is_file($envPath) || !preg_match('/^APP_KEY=base64:.+$/m', (string) file_get_contents($envPath))) {
        throw new RuntimeException('core/.env is missing or has no APP_KEY — run install.php first (only on a fresh install).');
    }

    // One-time rebrand: older installs carry APP_NAME=PromptVellum.
    $envContents = (string) file_get_contents($envPath);
    if (preg_match('/^APP_NAME=PromptVellum\s*$/m', $envContents)) {
        @file_put_contents($envPath, preg_replace('/^APP_NAME=PromptVellum\s*$/m', 'APP_NAME=PromptSewa', $envContents));
        $log[] = ['ok', 'APP_NAME rebranded to PromptSewa in .env'];
    }

    require $core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
    $app = require $core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $log[] = ['ok', 'Laravel booted ('.($app->version() ?? '').')'];

    $artisan = static function (string $command, array $params = []) use ($kernel): string {
        $exit = $kernel->call($command, $params);

        return trim($kernel->output());
    };

    $inMaintenance = false;
    try {
        $artisan('down');
        $inMaintenance = true;
        $log[] = ['ok', 'Maintenance mode ON'];
    } catch (Throwable $e) {
        $log[] = ['warn', 'Could not enable maintenance mode: '.$e->getMessage()];
    }

    try {
        $out = $artisan('migrate', ['--force' => true]);
        $log[] = ['ok', 'Migrations executed'];
        if ($out !== '') {
            $log[] = ['info', 'migrate: '.$out];
        }

        // Idempotent seeders (DemoContentSeeder + JustShipItAISeeder skip
        // themselves when data exists) — safe on every update.
        try {
            $artisan('db:seed', ['--force' => true]);
            $log[] = ['ok', 'Seeders executed (idempotent — existing data preserved)'];
        } catch (Throwable $e) {
            $log[] = ['warn', 'Seeding skipped: '.$e->getMessage()];
        }

        foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
            try {
                $artisan($cmd);
                $log[] = ['ok', $cmd.' ✓'];
            } catch (Throwable $e) {
                $log[] = ['warn', $cmd.' failed: '.$e->getMessage()];
            }
        }

        // Re-sync compiled assets into the docroot.
        $src = $core.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build';
        $dst = __DIR__.DIRECTORY_SEPARATOR.'build';
        if (is_dir($src)) {
            if (is_dir($dst)) {
                recurse_rmdir($dst);
            }
            recurse_copy($src, $dst);
            $log[] = ['ok', 'Compiled assets re-synced to public_html/build'];
        } else {
            $log[] = ['warn', 'core/public/build not found — upload the release with built assets.'];
        }

        try {
            $artisan('storage:link');
        } catch (Throwable) {
            // cosmetic only — /storage/ is served by .htaccess rewrite
        }
    } finally {
        if ($inMaintenance) {
            try {
                $artisan('up');
                $log[] = ['ok', 'Maintenance mode OFF — site is live'];
            } catch (Throwable $e) {
                $log[] = ['bad', 'CRITICAL: maintenance mode could not be disabled: '.$e->getMessage().' — run `php artisan up` in cPanel Terminal.'];
            }
        }
    }

    return $log;
}

/* =========================================================================
 * HTTP flow
 * ====================================================================== */

$core = detect_core_path();

render_head('Update');
echo '<h1><span class="pv">PV</span>PromptSewa Updater</h1>';
echo '<p class="sub">Runs migrations + caches + asset sync after uploading a new release.</p>';

if ($core === null || !is_file($core.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
    echo '<div class="card"><span class="bad">✘ core/ (or core/vendor/) not found.</span> Upload the release first — see docs/DEPLOYMENT.md.</div>';
    render_foot();
    exit;
}

$tokenFile = __DIR__.'/.update-token';

// First visit with no token file on disk? Generate one automatically —
// no cPanel detour needed. The admin copies it once; it's remembered
// across future updates via the browser session.
if (! is_file($tokenFile)) {
    @file_put_contents($tokenFile, bin2hex(random_bytes(16)));
}
$token = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';

if ($token === '') {
    echo '<div class="card"><span class="bad">✘ Could not create .update-token automatically.</span> Create the file manually in cPanel → File Manager (Show Hidden Files) with any long random string, then reload.</div>';
    render_foot();
    exit;
}

// Session trust: once you've proven you know the token this session,
// don't ask again on subsequent updates.
if (! empty($_SESSION['update_authenticated'])) {
    $tokenOk = true;
} else {
    $given = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
    $tokenOk = hash_equals($token, $given);
    if ($tokenOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['update_authenticated'] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The update token IS the credential here (random 128-bit, never baked
    // into the browser session) — CI/CD posts it without a PHP session, so
    // a matching token is accepted without the CSRF field.
    if (! $tokenOk) {
        echo '<div class="card"><span class="bad">✘ Invalid update token.</span> <a href="update.php">Try again</a>.</div>';
        render_foot();
        exit;
    }

    @set_time_limit(600);
    $log = [];
    echo '<h2>Updating…</h2><div class="card"><ul class="log">';

    try {
        // Mode A: direct zip upload through this page (admin-panel style).
        if (isset($_FILES['release_zip']) && ($_FILES['release_zip']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = $_FILES['release_zip'];
            if (($upload['error'] ?? 1) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload failed (PHP error code '.$upload['error'].') — the file may exceed the host\'s upload_max_filesize (~'.ini_get('upload_max_filesize').'). Upload via cPanel instead, or raise the limit in MultiPHP INI Editor.');
            }
            if (! is_uploaded_file($upload['tmp_name'])) {
                throw new RuntimeException('Invalid upload.');
            }
            foreach (extract_release_zip($upload['tmp_name'], $core, __DIR__) as [$level, $line]) {
                echo '<li><span class="'.e($level).'">'.e($line).'</span></li>';
            }
        }

        $log = run_update($core);
        $failed = false;
    } catch (Throwable $e) {
        $log[] = ['bad', 'FATAL: '.$e->getMessage()];
        $failed = true;
    }
    foreach ($log as [$level, $line]) {
        echo '<li><span class="'.e($level).'">'.e($line).'</span></li>';
    }
    echo '</ul></div>';

    echo $failed
        ? '<div class="card"><p>Check <code>core/storage/logs/laravel.log</code> for details, and confirm you uploaded the full release (including <code>vendor/</code>).</p></div>'
        : '<div class="card"><p><span class="ok">Update complete.</span> Now delete <code>update.php</code> (or keep it — it is token-protected).</p>'
        .'</div>';

    render_foot();
    exit;
}

$_SESSION['update_csrf'] = bin2hex(random_bytes(16));

$alreadyExtracted = is_file($core.DIRECTORY_SEPARATOR.'.env')
    && @filemtime($core.DIRECTORY_SEPARATOR.'vendor') !== false
    && ! empty($_SESSION['update_authenticated']);

echo '<div class="card">'
    .'<h2 style="margin-top:0;border:0">Run the update</h2>'
    .'<p class="hint"><b>Easiest — everything-in-one:</b> choose the release zip below and submit. It is extracted, migrated, seeded and brought back online in one step.<br><b>Already extracted via cPanel?</b> Leave the file empty and just submit.<br>The site briefly enters maintenance mode.</p>'
    .'<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="'.e($_SESSION['update_csrf']).'">'
    .'<label>Release zip (choose file, or leave empty if already extracted)</label>'
    .'<input type="file" name="release_zip" accept=".zip">'
    .'<label style="margin-top:14px">Update token <span style="font-weight:400;color:#78716c">(shown once — copy it somewhere safe)</span></label>'
    .'<input type="password" name="token" required placeholder="Paste the token from .update-token">'
    .'<p class="hint" style="margin-top:8px">Your token: <code style="user-select:all">'.e($token).'</code></p>'
    .'<p style="margin-top:18px"><button type="submit">⬆ Update now</button></p></form>'
    .'</div>';

render_foot();
