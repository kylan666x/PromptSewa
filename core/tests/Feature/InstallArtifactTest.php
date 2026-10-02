<?php

/**
 * v1.7.5 — the FRESH-INSTALL artifact (deploy/build-install-zip.php).
 *
 * An update zip is code-only; a fresh install has no vendor/ and no .env, so
 * the install zip carries everything install.php needs. That makes it the
 * most dangerous artifact we ship: the two things that must NEVER be inside
 * it are the dev `.env` (a known APP_KEY + a dev SQLite path) and this
 * repository's `.update-token` (a working update panel for anyone who reads
 * the repo). The builder self-audits and fails the build — this test locks
 * the audit's invariants so a future edit to the builder cannot quietly drop
 * one, and locks the installer bug that made EVERY fresh install fail.
 *
 * The installer bug (fixed in v1.7.5): `users.username` became NOT NULL in
 * v1.4.1 and install.php still created the admin without one, so the wizard
 * died with "NOT NULL constraint failed: users.username". It never surfaced
 * because install.php had not been run since before v1.4.1.
 */
/**
 * The artifact path, resolved INSIDE a test (Pest loads this file before the
 * container boots, so config() is unavailable at file scope).
 */
function v175InstallZipPath(): string
{
    return dirname(__DIR__, 3).'/dist/promptsewa-'.config('app.version').'-install.zip';
}

test('the install artifact exists and is a self-contained installable tree', function () {
    $installZip = v175InstallZipPath();

    if (! is_file($installZip)) {
        $this->markTestSkipped('Install zip not built — run: php deploy/build-install-zip.php');
    }

    $zip = new ZipArchive();
    expect($zip->open($installZip))->toBeTrue('install zip could not be opened');

    // Everything install.php boots with.
    foreach ([
        'core/artisan',
        'core/bootstrap/app.php',
        'core/composer.json',
        'core/composer.lock',
        'core/vendor/autoload.php',       // NO composer on a shared host
        'core/public/build/manifest.json', // compiled assets, never a stale build
        'public_html/index.php',
        'public_html/.htaccess',
        'public_html/.user.ini',
        'public_html/install.php',
        'public_html/update.php',
    ] as $member) {
        expect($zip->locateName($member))->not->toBeFalse("install zip is missing {$member}");
    }

    // The runtime skeleton an empty extract needs to boot.
    foreach ([
        'core/storage/framework/cache/data/.gitkeep',
        'core/storage/framework/sessions/.gitkeep',
        'core/storage/framework/views/.gitkeep',
        'core/storage/logs/.gitkeep',
        'core/storage/app/public/.gitkeep',
    ] as $skeleton) {
        expect($zip->locateName($skeleton))->not->toBeFalse("install zip is missing the runtime skeleton {$skeleton}");
    }

    $zip->close();
});

test('the install artifact ships no secrets, no host-local state and no dev surface', function () {
    $installZip = v175InstallZipPath();

    if (! is_file($installZip)) {
        $this->markTestSkipped('Install zip not built — run: php deploy/build-install-zip.php');
    }

    $zip = new ZipArchive();
    $zip->open($installZip);

    $forbidden = [];
    // Directory skeleton markers are REQUIRED for a bare extract to boot —
    // the audit exempts exactly these, so the test must too.
    $allowedMarkers = ['.gitkeep', '.gitignore'];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $leaf = basename($name);

        if (in_array($leaf, $allowedMarkers, true)
            && (str_starts_with($name, 'core/storage/') || str_starts_with($name, 'core/bootstrap/cache/'))) {
            continue;
        }

        // install.php writes .env with a fresh APP_KEY; it also GENERATES a
        // random .update-token. Shipping either one hands over the app.
        if (str_contains($name, '.env') && ! str_ends_with($name, '.env.example')) {
            $forbidden[] = $name.' (.env must be written by install.php)';
        }
        if ($name === 'public_html/.update-token') {
            $forbidden[] = $name.' (install.php generates a fresh random token)';
        }
        if (str_contains($name, '.sqlite')) {
            $forbidden[] = $name.' (dev database)';
        }
        // Host-local caches are the v1.4.4 poison; storage contents are runtime.
        if (str_starts_with($name, 'core/bootstrap/cache/')) {
            $forbidden[] = $name;
        }
        if (str_starts_with($name, 'core/storage/logs/') || str_starts_with($name, 'core/storage/framework/views/')) {
            $forbidden[] = $name;
        }
        // Dev-only surface: vendor/ here is --no-dev, so the suite cannot run.
        if (str_starts_with($name, 'core/tests/') || str_ends_with($name, 'phpunit.xml')) {
            $forbidden[] = $name;
        }
        if (str_contains($name, 'core/node_modules/')) {
            $forbidden[] = $name;
        }
        // Dev tooling must not be in a production vendor/.
        foreach (['pestphp', 'phpunit', 'mockery', 'fakerphp', 'phpstan'] as $package) {
            if (str_starts_with($name, 'core/vendor/'.$package.'/')) {
                $forbidden[] = $name.' (dev dependency — vendor/ must be built --no-dev)';
            }
        }
    }
    $zip->close();

    expect($forbidden)->toBe([], "install zip must not ship:\n".implode("\n", array_unique($forbidden)));
});

test('install.php creates the admin WITH a username (users.username is NOT NULL since v1.4.1)', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/deploy/public_html/install.php');

    // The admin row must carry a username, and the handle must be derived and
    // collision-checked — /creators/{handle} resolves from this column.
    expect($source)->toContain("'username' => \$username")
        ->and($source)->toContain('function unique_admin_username(')
        ->and($source)->toContain("App\\Models\\User::query()->where('username', \$candidate)->exists()")
        ->and($source)->toContain('NOT NULL constraint failed');

    // Regression lock, not a guess: an unkeyed User::create in the installer
    // is exactly the bug — make it impossible to reintroduce silently.
    expect(substr_count($source, 'App\Models\User::create('))->toBe(1);
});

test('install.php never claims seeding succeeded when D4 refuses it in production', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/deploy/public_html/install.php');

    // install.php writes APP_ENV=production, and DemoContentSeeder /
    // JustShipItAISeeder / BulkCatalogSeeder HARD-REFUSE there (D4). The old
    // log line asserted success unconditionally — a silent lie on every
    // fresh install. The honest branch reports the refusal + real counts.
    expect($source)->toContain("str_contains(\$output, 'REFUSED')")
        ->and($source)->toContain('Demo seeders REFUSED in production (D4)')
        ->and($source)->not->toContain("'Demo content seeded (categories, prompts, JustShipItAI profile)'");
});

test('the install builder keeps its audit rules and its version in lockstep', function () {
    $builderPath = dirname(__DIR__, 3).'/deploy/build-install-zip.php';
    $source = (string) file_get_contents($builderPath);

    // The artifact name is versioned from the same chip the update zip uses,
    // so a founder never has to guess which build they are uploading.
    expect($source)->toContain("const APP_VERSION = '".config('app.version')."';");

    // The audit is the permanent wall: a forbidden-pattern list plus the
    // required-members list. Removing either re-opens the v1.4.4 door.
    foreach ([
        'host-local config/route cache',
        'runtime storage contents',
        '.env must be written by install.php',
        'install.php generates a fresh random token',
        'dev database',
    ] as $rule) {
        expect($source)->toContain($rule);
    }

    // Dev packages asserted absent proves `--no-dev` actually ran.
    expect($source)->toContain("['pestphp', 'phpunit', 'mockery', 'fakerphp', 'phpstan']")
        ->and($source)->toContain("locateName('core/vendor/'.\$devPackage)");

    expect($source)->toContain('--no-dev')
        ->and($source)->toContain("'tests/'");
});
