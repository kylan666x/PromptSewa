<?php

/* -------------------------------------------------------------------------
| Build the PromptSewa FRESH-INSTALL zip.
| -------------------------------------------------------------------------
|
| This is the sibling of build-update-zip.php, NOT a superset of it. An
| update zip is code-only (the live host already has vendor/ and a .env);
| a fresh install has NEITHER, so this artifact must carry everything the
| wizard needs to boot:
|
|   core/          the app + production vendor/ + compiled public/build
|   public_html/   the docroot: index.php, .htaccess, .user.ini,
|                  install.php, update.php, README.md
|
| The founder then: uploads + extracts at /home/USER/, creates a MySQL
| database, visits https://domain/install.php, follows the wizard, deletes
| install.php, adds the cron. No SSH, no Composer, no Node on the host.
|
| WHAT IS DELIBERATELY NOT IN HERE (and why):
|   .env            install.php writes it with a fresh APP_KEY. Shipping the
|                   dev .env would ship a known APP_KEY + a dev SQLite path.
|   .update-token   install.php generates a random one. Shipping this repo's
|                   token would hand anyone who reads the repo a working
|                   update panel on the new host.
|   bootstrap/cache host-local (the v1.4.4 poison); only .gitignore rides.
|   storage/**      runtime contents; only the directory skeleton + .gitignore.
|   database.sqlite the host runs MySQL (install.php warns on SQLite).
|   tests/ + phpunit.xml
|                   production artifact. vendor/ here is --no-dev, so the
|                   suite could not run on the host anyway. (Update zips keep
|                   shipping them — they merge over an existing checkout.)
|
| USAGE
|   C:\PHP\bin\php.exe deploy/build-install-zip.php
|   C:\PHP\bin\php.exe deploy/build-install-zip.php --stage-only
|
| Result: dist/promptsewa-<VERSION>-install.zip (+ SHA-256). The build FAILS
| on its own hygiene audit, so a poisoned artifact cannot ship.
*/

declare(strict_types=1);

const APP_VERSION = '1.9.0';

$repoRoot = dirname(__DIR__);
$core = $repoRoot.DIRECTORY_SEPARATOR.'core';
$publicHtml = $repoRoot.DIRECTORY_SEPARATOR.'deploy'.DIRECTORY_SEPARATOR.'public_html';
$dist = $repoRoot.DIRECTORY_SEPARATOR.'dist';
$staging = $dist.DIRECTORY_SEPARATOR.'install-staging-'.APP_VERSION;
$zipPath = $dist.DIRECTORY_SEPARATOR.'promptsewa-'.APP_VERSION.'-install.zip';
$stageOnly = in_array('--stage-only', $argv, true);

function out(string $line = ''): void
{
    echo $line.PHP_EOL;
}

$fail = static function (string $message): never {
    fwrite(STDERR, 'ERROR: '.$message.PHP_EOL);
    exit(1);
};

function recurse_rmdir(string $dir): void
{
    if (! is_dir($dir)) {
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

/** Recursive copy used for the pre-composer app tree. */
function copy_dir(string $src, string $dst): void
{
    if (! is_dir($dst)) {
        mkdir($dst, 0777, true);
    }
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $s = $src.DIRECTORY_SEPARATOR.$item;
        $d = $dst.DIRECTORY_SEPARATOR.$item;
        if (is_link($s)) {
            continue;
        }
        is_dir($s) ? copy_dir($s, $d) : copy($s, $d);
    }
}

/** Relative POSIX paths (dirs end with '/') that must never be staged. */
$excludedDirs = [
    // production vendor (the dev one has pest/phpunit/mockery in it)
    'vendor/',
    'node_modules/',
    'bootstrap/cache/',
    'storage/app/',
    'storage/framework/cache/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/framework/testing/',
    'storage/logs/',
    'storage/pail/',
    // dev-only test suite — not part of a production install artifact
    'tests/',
    // symlink; install.php runs storage:link on the host
    'public/storage/',
    '.git/',
    '.github/',
    '.agents/',
    '.claude/',
    '.zed/',
    '.phpunit.cache/',
    '.idea/',
];

$excludedFiles = [
    '.env',
    '.env.backup',
    '.env.production',
    '.env.local',
    'database/database.sqlite',
    'phpunit.xml',
    '.phpunit.result.cache',
    'public/hot',
    'README.md',      // core-local dev notes; deploy/public_html/README.md is the installer's
    '.editorconfig',
    '.DS_Store',
];

/** .gitignore placeholders that DO ride along so a bare extract is bootable. */
$treeExceptions = [
    'bootstrap/cache/.gitignore',
    'storage/framework/.gitignore',
    'storage/framework/cache/.gitignore',
    'storage/framework/sessions/.gitignore',
    'storage/framework/views/.gitignore',
    'storage/framework/testing/.gitignore',
    'storage/logs/.gitignore',
    'storage/app/.gitignore',
    'storage/pail/.gitignore',
];

/** Docroot allow-list. `.update-token` is NEVER here (see header). */
$publicHtmlAllowlist = [
    'index.php',
    '.htaccess',
    '.user.ini',
    'install.php',
    'update.php',
    'README.md',
];

if (! extension_loaded('zip')) {
    $fail('ext-zip is not enabled — enable it in php.ini.');
}
if (! is_dir($core)) {
    $fail('core/ not found — run this from the repository root checkout.');
}

out('PromptSewa FRESH-INSTALL builder — v'.APP_VERSION);
out('=============================================');

/* -------------------------------------------------------------------------
 * 1) Stage core/
 * ---------------------------------------------------------------------- */
out('');
out('1) Staging core/…');
recurse_rmdir($staging);
$coreStaging = $staging.DIRECTORY_SEPARATOR.'core';
mkdir($coreStaging, 0777, true);

$skip = static function (string $rel) use ($excludedDirs, $excludedFiles): bool {
    if (in_array($rel, $excludedFiles, true)) {
        return true;
    }
    // S1b (v1.9.0): the audit below bans ANY `.sqlite` entry — a local
    // preview copy or backup must not even be staged.
    if (str_contains($rel, '.sqlite')) {
        return true;
    }
    // Same for local env files (`.env.preview`, `.env.local`, …): the audit
    // bans them, and install.php writes the real `.env` anyway. `.env.example`
    // is the documented template and DOES ship in a fresh install.
    if (preg_match('/(^|\/)\.env($|\.)/', $rel) && ! str_ends_with($rel, '.env.example')) {
        return true;
    }
    foreach ($excludedDirs as $dir) {
        if ($rel === rtrim($dir, '/') || str_starts_with($rel, $dir)) {
            return true;
        }
    }

    return false;
};

$coreFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($core, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $item) {
    if (! $item->isFile() || is_link($item->getPathname())) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($core) + 1));
    if ($skip($rel)) {
        continue;
    }
    $coreFiles[] = $rel;
}

// The directory skeleton Laravel needs at boot (an empty dir does not
// survive every zip round-trip, so each gets a .gitkeep).
$skeleton = [
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'storage/app/public',
    'storage/app/private',
    'storage/app/proofs',
    'storage/app/bug-reports',
];
foreach ($skeleton as $dir) {
    $coreFiles[] = $dir.'/.gitkeep';
}
foreach ($treeExceptions as $marker) {
    if (! in_array($marker, $coreFiles, true)) {
        $coreFiles[] = $marker;
    }
}
sort($coreFiles);

/* -------------------------------------------------------------------------
 * 2) vendor/ — production only (composer install --no-dev)
 * ---------------------------------------------------------------------- */
out('');
out('2) Building production vendor/…');
$composerPhar = 'C:/PHP/bin/composer.phar';
$composerRunner = null;
if (is_file($composerPhar)) {
    $composerRunner = escapeshellarg(PHP_BINARY).' '.escapeshellarg($composerPhar);
} else {
    $localPhar = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'composer.phar';
    if (is_file($localPhar)) {
        $composerRunner = escapeshellarg(PHP_BINARY).' '.escapeshellarg($localPhar);
    } else {
        exec('command -v composer 2>/dev/null', $probe, $probeCode);
        if ($probeCode === 0) {
            $composerRunner = 'composer';
        }
    }
}

if ($composerRunner === null) {
    $fail('No Composer available (looked for composer.phar next to the PHP binary and composer on PATH). '
        .'A fresh install MUST ship vendor/ — put composer.phar at '.dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'composer.phar and re-run.');
}

// composer needs composer.json/lock + the autoloadable app tree in place.
foreach (['app', 'config', 'database', 'routes', 'resources'] as $dir) {
    copy_dir($core.DIRECTORY_SEPARATOR.$dir, $coreStaging.DIRECTORY_SEPARATOR.$dir);
}
mkdir($coreStaging.DIRECTORY_SEPARATOR.'bootstrap', 0777, true);
copy($core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php',
    $coreStaging.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php');
foreach (['composer.json', 'composer.lock'] as $manifestFile) {
    copy($core.DIRECTORY_SEPARATOR.$manifestFile, $coreStaging.DIRECTORY_SEPARATOR.$manifestFile);
}

$cmd = $composerRunner.' install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts 2>&1';
$oldCwd = getcwd();
chdir($coreStaging);
exec($cmd, $composerOut, $composerExit);
chdir($oldCwd);
$composerOut = implode(PHP_EOL, $composerOut);

if ($composerExit !== 0 || ! is_dir($coreStaging.DIRECTORY_SEPARATOR.'vendor')) {
    out('  composer output:');
    out('  '.preg_replace('/\s+/', ' ', substr($composerOut, -600)));
    $fail('composer install --no-dev failed — cannot ship an install zip without vendor/.');
}
$vendorPkgs = count(glob($coreStaging.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'*') ?: []);
out("  vendor/ ready ({$vendorPkgs} entries, --no-dev)");

// The pre-composer copy bypasses $excludedFiles, so scrub what it dragged in.
// (The hygiene audit below still asserts this — the scrub is belt, the audit
// is braces: the v1.4.4 lesson is that the audit must never be the only wall.)
foreach (['database/database.sqlite', 'database/preview.sqlite', '.env', '.env.backup', '.env.production'] as $leak) {
    if (is_file($coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $leak))) {
        @unlink($coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $leak));
        out("  scrubbed pre-staged {$leak}");
    }
}

/* -------------------------------------------------------------------------
 * 3) Copy every staged file into place (after composer needs the tree)
 * ---------------------------------------------------------------------- */
out('');
out('3) Writing core/ files…');
$written = 0;
foreach ($coreFiles as $rel) {
    $src = $core.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (! is_file($src)) {
        continue; // skeleton markers are created below
    }
    $dst = $coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (! is_dir(dirname($dst))) {
        mkdir(dirname($dst), 0777, true);
    }
    copy($src, $dst);
    $written++;
}
// Skeleton markers for directories that are empty by design.
foreach ($skeleton as $dir) {
    $path = $coreStaging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $dir).DIRECTORY_SEPARATOR.'.gitkeep';
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    if (! is_file($path)) {
        file_put_contents($path, '');
    }
}
out("  {$written} files + ".count($skeleton).' runtime dirs');

/* -------------------------------------------------------------------------
 * 4) Compiled assets must be present and current
 * ---------------------------------------------------------------------- */
out('');
out('4) Verifying compiled assets…');
if (! is_dir($coreStaging.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build')) {
    $fail('core/public/build is missing — run `npm run build` in core/ first, then re-run.');
}
$manifest = json_decode((string) file_get_contents(
    $coreStaging.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json'
), true);
foreach (['resources/js/app.js', 'resources/css/app.css'] as $asset) {
    if (! isset($manifest[$asset]['file'])) {
        $fail("manifest.json has no entry for {$asset} — the build is stale.");
    }
    out('  '.$asset.' -> '.$manifest[$asset]['file']);
}

/* -------------------------------------------------------------------------
 * 5) Docroot
 * ---------------------------------------------------------------------- */
out('');
out('5) Staging public_html/…');
$publicHtmlStaging = $staging.DIRECTORY_SEPARATOR.'public_html';
mkdir($publicHtmlStaging, 0777, true);
foreach ($publicHtmlAllowlist as $name) {
    $src = $publicHtml.DIRECTORY_SEPARATOR.$name;
    if (! is_file($src)) {
        $fail("public_html allow-list file missing: {$name}");
    }
    copy($src, $publicHtmlStaging.DIRECTORY_SEPARATOR.$name);
}
out('  '.implode(', ', $publicHtmlAllowlist));

if ($stageOnly) {
    out('');
    out('Staged tree ready at '.$staging.' (--stage-only)');
    exit(0);
}

/* -------------------------------------------------------------------------
 * 6) Zip
 * ---------------------------------------------------------------------- */
out('');
out('6) Creating '.basename($zipPath).'…');

// Reproducibility: copy() stamps every staged file with the CURRENT time, so
// two builds of identical content produced different bytes — and therefore
// different SHA-256s. A published hash the founder cannot reproduce is worse
// than no hash at all: it turns "is this the audited artifact?" into a
// question nobody can answer. Pin every staged mtime to the release date, so
// rebuilding is byte-identical and the hash in the handoff is verifiable.
const RELEASE_TIMESTAMP = '2026-10-02 12:00:00';
$stamp = strtotime(RELEASE_TIMESTAMP);
$stampTree = static function (string $dir) use (&$stampTree, $stamp): void {
    touch($dir, $stamp);
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir.DIRECTORY_SEPARATOR.$item;
        if (is_dir($path) && ! is_link($path)) {
            $stampTree($path);
        } else {
            @touch($path, $stamp);
        }
    }
};
$stampTree($staging);
out('  pinned mtimes to '.RELEASE_TIMESTAMP.' (reproducible builds)');

if (is_file($zipPath)) {
    @unlink($zipPath);
}
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    $fail('Could not create '.$zipPath);
}
$baseLen = strlen($staging.DIRECTORY_SEPARATOR);
$zipIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($zipIterator as $file) {
    if (! $file->isFile()) {
        continue;
    }
    $zip->addFile($file->getPathname(), str_replace('\\', '/', substr($file->getPathname(), $baseLen)));
}
$zip->close();

/* -------------------------------------------------------------------------
 * 7) Self-audit — a violation is a BUILD FAILURE, never a warning.
 *    The v1.4.4 lesson: a host-local artifact in a shipped zip poisons prod.
 * ---------------------------------------------------------------------- */
out('');
out('7) Hygiene audit…');
$audit = new ZipArchive();
if ($audit->open($zipPath) !== true) {
    $fail('Built zip could not be reopened for the audit.');
}
$violations = [];
$entries = $audit->numFiles;
for ($i = 0; $i < $entries; $i++) {
    $name = (string) $audit->getNameIndex($i);
    $stripped = preg_replace('#^(core|public_html)/#', '', $name);

    // Runtime skeletons are allowed; their CONTENTS are not.
    if (in_array($stripped, $treeExceptions, true) || str_ends_with($stripped, '/.gitkeep')) {
        continue;
    }

    if (str_starts_with($name, 'core/bootstrap/cache/')) {
        $violations[] = $name.' (host-local config/route cache — the v1.4.4 poison)';
    }
    if (str_starts_with($name, 'core/storage/logs/')
        || str_starts_with($name, 'core/storage/framework/views/')
        || str_starts_with($name, 'core/storage/framework/sessions/')
        || str_starts_with($name, 'core/storage/framework/cache/')) {
        $violations[] = $name.' (runtime storage contents)';
    }
    if (str_contains($name, '.env') && ! str_ends_with($name, '.env.example')) {
        $violations[] = $name.' (.env must be written by install.php)';
    }
    if (str_contains($name, '.sqlite')) {
        $violations[] = $name.' (dev database)';
    }
    if ($name === 'public_html/.update-token') {
        $violations[] = $name.' (install.php generates a fresh random token)';
    }
    if (str_starts_with($name, 'core/tests/') || str_ends_with($name, 'phpunit.xml')) {
        $violations[] = $name.' (dev test suite — not part of a production install)';
    }
    if (str_contains($name, 'core/node_modules/')) {
        $violations[] = $name.' (node_modules)';
    }
}

// Dev tooling inside vendor/ — proves --no-dev actually ran.
foreach (['pestphp', 'phpunit', 'mockery', 'fakerphp', 'phpstan'] as $devPackage) {
    if ($audit->locateName('core/vendor/'.$devPackage) !== false) {
        $violations[] = "core/vendor/{$devPackage} (dev dependency in a production vendor/)";
    }
}

// Required members.
$required = [
    'core/artisan',
    'core/bootstrap/app.php',
    'core/vendor/autoload.php',
    'core/composer.json',
    'core/composer.lock',
    'core/database/database.sqlite', // must be ABSENT — asserted below
    'core/public/build/manifest.json',
    'public_html/index.php',
    'public_html/install.php',
    'public_html/update.php',
    'public_html/.htaccess',
    'public_html/.user.ini',
];
$missing = [];
foreach ($required as $name) {
    if ($name === 'core/database/database.sqlite') {
        if ($audit->locateName($name) !== false) {
            $violations[] = $name.' (dev database must not ship)';
        }
        continue;
    }
    if ($audit->locateName($name) === false) {
        $missing[] = $name;
    }
}
$audit->close();

if ($violations !== [] || $missing !== []) {
    if ($violations !== []) {
        out('FORBIDDEN ENTRIES:');
        foreach ($violations as $v) {
            out('  '.$v);
        }
    }
    if ($missing !== []) {
        out('MISSING REQUIRED MEMBERS:');
        foreach ($missing as $m) {
            out('  '.$m);
        }
    }
    $fail('Install zip failed its hygiene audit — fix the builder, never the audit.');
}

$sizeMb = round((float) filesize($zipPath) / 1048576, 2);
$sha = hash_file('sha256', $zipPath) ?: 'n/a';

out('  hygiene audit: CLEAN (0 forbidden entries)');
out('');
out('dist/promptsewa-'.APP_VERSION.'-install.zip built: '.$entries.' entries, '.$sizeMb.' MB');
out('SHA-256: '.$sha);
out('');
out('Install on a NEW cPanel server:');
out('  1. Create the MySQL database + user (ALL PRIVILEGES) in cPanel.');
out('  2. Upload this zip to /home/USER/ and Extract it there, so you end up');
out('     with /home/USER/core and /home/USER/public_html.');
out('  3. Open https://your-domain.com/install.php and follow the wizard');
out('     (it writes .env, migrates, creates the admin account, links storage,');
out('     rebuilds caches and generates a fresh update token).');
out('  4. DELETE public_html/install.php when it reports success.');
out('  5. Add the cron the wizard shows you: cd core && php artisan schedule:run');
out('  6. Future upgrades: upload a promptsewa-*-update.zip and run update.php.');
