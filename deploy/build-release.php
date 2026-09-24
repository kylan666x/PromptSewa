<?php

/*
|--------------------------------------------------------------------------
| PromptVellum — Release Builder (deploy/build-release.php)
|--------------------------------------------------------------------------
|
| Produces the upload-ready distribution:
|
|   dist/promptvellum-upload.zip
|   ├── core/          <- extract at /home/USER/  (app + vendor + built assets)
|   └── public_html/   <- extract at /home/USER/  (front controller, .htaccess,
|                        .user.ini, install.php, update.php)
|
| Install/update on the server afterwards = open install.php (first time)
| or update.php (updates) — no SSH, no Composer, no Node on the host.
|
| Usage:
|   php deploy/build-release.php            # build dist/promptvellum-upload.zip
|   php deploy/build-release.php --no-zip   # stage dist/staging/ tree only
|                                           # (used by the CI/CD FTP deploy job)
|
| Requires: PHP 8.2+ with ext-zip. Composer + Node are auto-detected for
| building vendor/ and public/build/ when they are not already present.
|
*/

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$coreSrc = $repoRoot.DIRECTORY_SEPARATOR.'core';
$deployPublicHtml = __DIR__.DIRECTORY_SEPARATOR.'public_html';
$distDir = $repoRoot.DIRECTORY_SEPARATOR.'dist';
$staging = $distDir.DIRECTORY_SEPARATOR.'staging';
$zipPath = $distDir.DIRECTORY_SEPARATOR.'promptvellum-upload.zip';
$noZip = in_array('--no-zip', $argv, true);

function out(string $line = ''): void
{
    echo $line.PHP_EOL;
}

function fail(string $message): never
{
    fwrite(STDERR, "✘ ERROR: {$message}".PHP_EOL);
    exit(1);
}

/** Copy a file/dir tree, skipping anything in $skip (relative POSIX paths, dirs end with /). */
function copy_tree(string $src, string $dst, array $skip = [], string $rel = ''): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0777, true);
    }
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $relPath = ($rel === '' ? '' : $rel.'/').$item;
        foreach ($skip as $pattern) {
            if (str_ends_with($pattern, '/')) {
                if ($relPath.'/' === $pattern || str_starts_with($relPath.'/', $pattern)) {
                    continue 2;
                }
            } elseif ($relPath === $pattern) {
                continue 2;
            }
        }
        $s = $src.DIRECTORY_SEPARATOR.$item;
        $d = $dst.DIRECTORY_SEPARATOR.$item;
        if (is_dir($s)) {
            copy_tree($s, $d, $skip, $relPath);
        } elseif (is_link($s)) {
            continue; // e.g. public/storage symlink — recreated on the server
        } else {
            copy($s, $d);
        }
    }
}

function recurse_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        is_dir($path) ? recurse_rmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

function run(string $cmd, string $cwd): string
{
    out("  \$ {$cmd}");
    $output = [];
    $code = 0;
    $oldCwd = getcwd();
    chdir($cwd);
    exec($cmd.' 2>&1', $output, $code);
    chdir($oldCwd);

    return implode(PHP_EOL, $output).($code === 0 ? '' : PHP_EOL."  (exit code {$code})");
}

function command_exists(string $cmd): bool
{
    $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
    $check = $isWindows ? 'where '.$cmd.' >nul 2>nul' : 'command -v '.$cmd.' >/dev/null 2>&1';
    exec($check, $out, $code);

    return $code === 0;
}

/** Locate a composer runner (composer on PATH, or composer.phar next to the PHP binary). */
function find_composer(): ?string
{
    if (command_exists('composer')) {
        return 'composer';
    }
    $candidates = [
        PHP_BINARY === '' ? '' : dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'composer.phar',
        dirname(__DIR__).DIRECTORY_SEPARATOR.'composer.phar',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return escapeshellarg(PHP_BINARY).' '.escapeshellarg($candidate);
        }
    }

    return null;
}

/* -------------------------------------------------------------------------
 * 0) Sanity checks
 * ---------------------------------------------------------------------- */

if (!is_dir($coreSrc)) {
    fail('core/ not found — run this script from the repository root layout.');
}

out('PromptVellum release builder');
out('============================');

/* -------------------------------------------------------------------------
 * 1) Fresh staging area
 * ---------------------------------------------------------------------- */

out('');
out('1) Preparing staging area…');
recurse_rmdir($staging);
if (!is_dir($distDir)) {
    mkdir($distDir, 0777, true);
}
$coreStaging = $staging.DIRECTORY_SEPARATOR.'core';
$publicHtmlStaging = $staging.DIRECTORY_SEPARATOR.'public_html';

$skipCore = [
    'vendor/',
    'node_modules/',
    'tests/',
    '.env',
    '.env.backup',
    '.env.production',
    'database/database.sqlite',
    'public/build/',           // copied below from a real build
    'public/storage',          // symlink, recreated by install.php
    'phpunit.xml',
    '.editorconfig',
    'storage/logs/',
    'storage/framework/cache/data/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/framework/cache/.namespaced?',
    'storage/app/private/',
    'storage/app/public/',
    '.agents/',
    '.claude/',
    '.zed/',
    '.mcp.json',
    'core/',               // safety: never recurse into a nested core copy
];
copy_tree($coreSrc, $coreStaging, $skipCore);

// Runtime skeleton — empty dirs so a bare extraction is immediately bootable.
foreach ([
    'storage/logs',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/app/public',
    'storage/app/private',
] as $empty) {
    $path = $coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $empty);
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}
// Empty dirs do not survive zip round-trips on all tools; drop marker files.
foreach ([
    'storage/logs/.gitkeep',
    'storage/framework/cache/data/.gitkeep',
    'storage/framework/sessions/.gitkeep',
    'storage/framework/views/.gitkeep',
    'storage/app/public/.gitkeep',
] as $marker) {
    @file_put_contents($coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $marker), '');
}

/* -------------------------------------------------------------------------
 * 2) vendor/ — production dependencies only
 * ---------------------------------------------------------------------- */

out('');
out('2) Building vendor/ (composer install --no-dev)…');
if (!is_dir($coreStaging.DIRECTORY_SEPARATOR.'vendor')) {
    if (is_dir($coreSrc.DIRECTORY_SEPARATOR.'vendor')) {
        out('  copying pre-built vendor/ from core/…');
        copy_tree($coreSrc.DIRECTORY_SEPARATOR.'vendor', $coreStaging.DIRECTORY_SEPARATOR.'vendor');
    } else {
    $composer = find_composer();
    if ($composer === null) {
        fail('Composer not found and no vendor/ staged. Install Composer or run with a pre-built vendor/.');
    }
    $output = run($composer.' install --no-dev --optimize-autoloader --no-interaction --no-progress', $coreStaging);
    out('  '.preg_replace('/\s+/', ' ', trim($output)) ?: '  (done)');
    }
}
out('  vendor/ ready');

/* -------------------------------------------------------------------------
 * 3) Built frontend assets (public/build)
 * ---------------------------------------------------------------------- */

out('');
out('3) Building frontend assets…');
if (!is_dir($coreSrc.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build')) {
    if (command_exists('npm')) {
        $out1 = run('npm ci --no-fund --no-audit --loglevel=error', $coreSrc);
        $out2 = run('npm run build', $coreSrc);
        out('  '.preg_replace('/\s+/', ' ', trim($out2)) ?: '  (done)');
    } else {
        fail('public/build missing and npm not found — run `npm run build` inside core/ first.');
    }
}
copy_tree(
    $coreSrc.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build',
    $coreStaging.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'
);
out('  public/build/ copied into staging');

/* -------------------------------------------------------------------------
 * 4) Docroot files (public_html)
 * ---------------------------------------------------------------------- */

out('');
out('4) Staging public_html/ docroot files…');
copy_tree($deployPublicHtml, $publicHtmlStaging);
out('  index.php, .htaccess, .user.ini, install.php, update.php staged');

/* -------------------------------------------------------------------------
 * 5) Zip it
 * ---------------------------------------------------------------------- */

if ($noZip) {
    out('');
    out('✔ Staged tree ready at dist/staging/ (--no-zip)');
    out('  core/         → FTP-mirror to /home/USER/core');
    out('  public_html/  → FTP-mirror to /home/USER/public_html');
    exit(0);
}

out('');
out('5) Creating dist/promptvellum-upload.zip…');
if (!extension_loaded('zip')) {
    fail('ext-zip is not enabled in php.ini — enable it (or run with --no-zip).');
}
if (is_file($zipPath)) {
    @unlink($zipPath);
}
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Could not create '.$zipPath);
}

$baseLen = strlen($staging.DIRECTORY_SEPARATOR);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$files = 0;
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();
    $entry = str_replace('\\', '/', substr($path, $baseLen));
    $zip->addFile($path, $entry);
    $files++;
}
$zip->close();

$sizeMb = round(filesize($zipPath) / 1048576, 1);
out("  {$files} files, {$sizeMb} MB");
out('');
out('✔ dist/promptvellum-upload.zip is ready.');
out('');
out('Install on cPanel (first time):');
out('  1. Upload the zip to /home/USER/ and Extract (cPanel File Manager).');
out('  2. Create the MySQL database + user (ALL PRIVILEGES).');
out('  3. Visit https://yourdomain.com/install.php and follow the wizard.');
out('  4. Delete install.php. Add the 1-minute cron (the wizard shows it).');
out('');
out('Update an existing install:');
out('  1. Upload the zip to /home/USER/ and Extract (overwrites).');
out('  2. Visit https://yourdomain.com/update.php, paste the token from');
out('     public_html/.update-token, run it.');
