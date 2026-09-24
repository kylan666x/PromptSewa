<?php

/*
|--------------------------------------------------------------------------
| Build the PromptSewa UPDATE zip — core/ only, code-only.
|--------------------------------------------------------------------------
|
| Unlike build-release.php (the full install zip with vendor/ + docroot
| files), this script ships ONLY the application code under core/ —
| no public_html/, no vendor/, no node_modules/. It is applied to a live
| installation via update.php (token-protected), which merges core/ over
| the existing installation, then migrates, seeds (idempotent), rebuilds
| caches and re-syncs compiled assets into the docroot.
|
| WHY code-only is safe:
|   - composer.json / composer.lock are unchanged since the 1.0.0 release,
|     so the live site's vendor/ is already correct. If you ever change
|     dependencies, ship a FULL release (build-release.php) instead — this
|     script warns when the lock file no longer matches the 1.0.0 baseline.
|
| Excluded from the zip (runtime/local-only, never shipped):
|   .env*, node_modules/, vendor/, storage/ runtime contents,
|   database/database.sqlite, .phpunit.cache/, public/hot,
|   public/storage symlink. Compiled assets in public/build/ ARE shipped.
|
| Usage:  C:\PHP\bin\php.exe deploy\build-update-zip.php
| Result: dist/promptsewa-<VERSION>-update.zip (+ SHA-256 checksum)
*/

declare(strict_types=1);

const APP_VERSION = '1.0.1-berry';

$repoRoot = dirname(__DIR__);
$zipPath = $repoRoot.DIRECTORY_SEPARATOR.'dist'
    .DIRECTORY_SEPARATOR.'promptsewa-'.APP_VERSION.'-update.zip';

// Paths (relative to core/) that are never shipped in an update zip.
$excludedDirs = [
    'node_modules',
    'vendor',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/framework/testing',
    'storage/logs',
    'storage/app/private',
    'storage/app/public',
    'storage/pail',
    '.phpunit.cache',
    '.git',
];

$excludedFiles = [
    '.env',
    '.env.backup',
    '.env.production',
    '.phpunit.result.cache',
    'database/database.sqlite',
    'public/hot',
];

// Dependencies must be unchanged since 1.0.0 for a code-only zip to be safe.
$baselineLock = $repoRoot.DIRECTORY_SEPARATOR.'deploy'
    .DIRECTORY_SEPARATOR.'promptsewa-main-1-0-0.zip';

function out(string $line = ''): void
{
    echo $line.PHP_EOL;
}

$fail = static function (string $message): never {
    fwrite(STDERR, "ERROR: {$message}".PHP_EOL);
    exit(1);
};

if (! extension_loaded('zip')) {
    $fail('ext-zip is not enabled — enable it in php.ini.');
}

$core = $repoRoot.DIRECTORY_SEPARATOR.'core';
if (! is_dir($core)) {
    $fail('core/ not found — run this script from the repository root checkout.');
}

// --- Dependency guard -------------------------------------------------------
if (is_file($baselineLock)) {
    $zip = new ZipArchive();
    if ($zip->open($baselineLock) === true) {
        $index = $zip->locateName('core/composer.lock', ZipArchive::FL_NODIR);
        $baselineLockHash = $index !== false ? md5((string) $zip->getFromIndex($index)) : null;
        $zip->close();

        $currentLockHash = is_file($core.'/composer.lock')
            ? md5((string) file_get_contents($core.'/composer.lock'))
            : null;

        if ($baselineLockHash !== null && $currentLockHash !== $baselineLockHash) {
            out('WARNING: composer.lock changed since the 1.0.0 baseline.');
            out('A code-only update zip will NOT update vendor/ on the live site.');
            out('If dependencies changed, build a FULL release instead: php deploy/build-release.php');
            out('');
        }
    }
}

// --- Collect files ----------------------------------------------------------
$files = [];
$skipDir = static function (string $relative) use ($excludedDirs): bool {
    foreach ($excludedDirs as $dir) {
        if ($relative === $dir || str_starts_with($relative, $dir.'/')) {
            return true;
        }
    }

    return false;
};

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($core, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

/** @var SplFileInfo $item */
foreach ($iterator as $item) {
    if (! $item->isFile() || is_link($item->getPathname())) {
        continue;
    }

    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($core) + 1));

    if ($skipDir($relative) || in_array($relative, $excludedFiles, true)) {
        continue;
    }

    $files[] = $relative;
}

sort($files);

if ($files === []) {
    $fail('No files collected from core/ — nothing to ship.');
}

// --- Build the zip ----------------------------------------------------------
if (is_file($zipPath)) {
    @unlink($zipPath);
}
@mkdir(dirname($zipPath), 0777, true);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    $fail("Could not create {$zipPath}");
}

foreach ($files as $relative) {
    $zip->addFile(
        $core.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative),
        'core/'.$relative
    );
}

$zip->close();

$sizeMb = round((float) filesize($zipPath) / 1048576, 2);
$sha = hash_file('sha256', $zipPath) ?: 'n/a';

out("dist/promptsewa-".APP_VERSION."-update.zip built: ".count($files)." files, {$sizeMb} MB");
out("SHA-256: {$sha}");
out('');
out('Deploy steps (live site already installed — core-only update):');
out("  1. Upload dist/promptsewa-".APP_VERSION."-update.zip via cPanel File Manager (or upload it");
out('     directly through the update.php form — it extracts + updates in one step).');
out('  2. Open https://<your-domain>/update.php');
out('  3. Paste the token from public_html/.update-token, click "Update now"');
out('     (core/ merged, migrations run, idempotent seeders, caches rebuilt,');
out('      compiled assets re-synced to public_html/build, maintenance mode toggled).');
