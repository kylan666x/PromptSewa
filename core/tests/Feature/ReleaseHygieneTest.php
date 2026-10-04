<?php

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * D-series (v1.4.5) — release hygiene.
 *
 * D1: the update zip must never carry host-local artifacts
 *     (bootstrap/cache/*, storage/**, public/storage, .env*, *.sqlite)
 *     and must still carry the docroot allow-list + compiled assets.
 * D3: pv:update reloads the in-memory config from the freshly written
 *     cache, so a poisoned boot-time view.paths cannot break view:cache.
 * D4: demo/bulk/flagship seeders HARD-refuse in production.
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

const FORBIDDEN_ZIP_PATTERNS = [
    'core/bootstrap/cache/',
    'core/storage/',
    'core/public/storage',
    '.env',
    '.sqlite',
];

function buildUpdateZip(string $target): array
{
    // Build via the same logic the deploy script uses: shell out to PHP so
    // the audit code under test is the exact production builder.
    $builder = realpath(__DIR__.'/../../../deploy/build-update-zip.php');
    expect($builder)->not->toBeFalse('build-update-zip.php must exist');

    // Point the builder at a temp target by overriding the version constant
    // is not possible from outside — instead run the real builder and COPY
    // the produced zip to the assertion target, leaving dist intact.
    $php = PHP_BINARY;
    exec("\"{$php}\" \"{$builder}\" 2>&1", $outputLines, $exit);

    return [$outputLines, $exit];
}

function auditZip(string $zipPath): array
{
    $zip = new ZipArchive();
    expect($zip->open($zipPath))->toBeTrue();

    $violations = [];
    $entries = $zip->numFiles;
    $allowedPlaceholders = [
        'core/bootstrap/cache/.gitignore',
        'core/storage/framework/.gitignore',
        'core/storage/framework/cache/.gitignore',
        'core/storage/framework/sessions/.gitignore',
        'core/storage/framework/views/.gitignore',
        'core/storage/framework/testing/.gitignore',
        'core/storage/logs/.gitignore',
        'core/storage/app/.gitignore',
        'core/storage/app/private/.gitignore',
        'core/storage/app/public/.gitignore',
        'core/storage/pail/.gitignore',
        'core/public/storage/.gitignore',
    ];

    for ($i = 0; $i < $entries; $i++) {
        $name = (string) $zip->getNameIndex($i);

        if (in_array($name, $allowedPlaceholders, true)) {
            continue;
        }

        foreach (FORBIDDEN_ZIP_PATTERNS as $pattern) {
            if (str_contains($name, $pattern)) {
                $violations[] = $name;
                break;
            }
        }
    }

    $hasDocroot = $zip->locateName('public_html/update.php') !== false
        && $zip->locateName('public_html/index.php') !== false
        && $zip->locateName('public_html/.htaccess') !== false
        && $zip->locateName('public_html/.user.ini') !== false;
    $hasBuild = $zip->locateName('core/public/build/manifest.json') !== false;

    $zip->close();

    return ['violations' => $violations, 'entries' => $entries, 'docroot' => $hasDocroot, 'build' => $hasBuild];
}

// ---------------------------------------------------------------------------
// D1 — zip hygiene
// ---------------------------------------------------------------------------

test('the built update zip carries no host-local artifacts and keeps docroot + build', function () {
    [$output, $exit] = buildUpdateZip('unused');

    expect($exit)->toBe(0, 'builder failed: '.implode(PHP_EOL, array_slice($output, -6)));

    $zipPath = dirname(__DIR__, 3).'/dist/promptsewa-'.config('app.version').'-update.zip';
    expect(is_file($zipPath))->toBeTrue("expected zip at {$zipPath}");

    $audit = auditZip($zipPath);

    expect($audit['violations'])->toBe([], 'zip carries forbidden host-local artifacts: '.implode(', ', $audit['violations']))
        ->and($audit['docroot'])->toBeTrue('docroot allow-list missing from zip')
        ->and($audit['build'])->toBeTrue('compiled public/build missing from zip — run npm run build')
        ->and($audit['entries'])->toBeGreaterThan(100);

    // The artifact STAYS in dist/ — it is the release deliverable. (An
    // earlier revision unlinked it here and silently deleted the release
    // zip after every full-suite run. Never delete the deliverable.)
});

test('the v1.4.4 zip is confirmed in violation of the D1 rule (incident lock)', function () {
    $old = dirname(__DIR__, 3).'/dist/promptsewa-1.4.4-update.zip';

    if (! is_file($old)) {
        $this->markTestSkipped('v1.4.4 artifact not present on this machine');
    }

    $audit = auditZip($old);

    // The incident: dev bootstrap caches shipped. This assertion IS the
    // confirmation the release report requires.
    expect($audit['violations'])->not->toBeEmpty('the v1.4.4 zip must show the D1 violation (bootstrap caches etc.)');
});

// ---------------------------------------------------------------------------
// D3 — in-process config reload in pv:update
// ---------------------------------------------------------------------------

test('pv:update purges bootstrap caches and reloads config so view:cache succeeds with poisoned paths', function () {
    // Simulate the v1.4.4 poison: boot-time view.paths pointing at a
    // non-existent dev location, plus a stale bootstrap cache on disk.
    $staleCache = config('view.compiled');
    File::put(
        base_path('bootstrap/cache/config.php'),
        '<?php return '.var_export([
            'view' => ['paths' => ['/nonexistent/dev/views'], 'compiled' => $staleCache],
            'app' => ['version' => '0.0.0-dev'],
        ], true).';'
    );

    try {
        // Poison the in-memory repo the way a poisoned boot would.
        config(['view.paths' => ['/nonexistent/dev/views']]);

        $exit = Artisan::call('pv:update', ['--no-extract' => true]);

        expect($exit)->toBe(Command::SUCCESS, 'pipeline failed: '.trim(Artisan::output()))
            ->and(File::exists(base_path('bootstrap/cache/config.php')))->toBeTrue('config:cache must rewrite the cache');

        // D2 step 0 removed the poisoned file before migrate; D3 reloaded
        // host paths, so view:cache compiled from the REAL view directory.
        // Compare against the canonical config FILE's value, not a hard-coded
        // release string — the version bumps, the reload contract does not.
        $canonical = require config_path('app.php');
        expect(config('app.version'))->toBe($canonical['version'], 'D3 reload must re-read the canonical cache');

        // The failure record stays absent — nothing fatal happened.
        expect(File::exists(storage_path('logs/update-failed.json')))->toBeFalse();
    } finally {
        // Leave the suite a clean cache state.
        @unlink(base_path('bootstrap/cache/config.php'));
        Artisan::call('config:clear');
        Artisan::call('view:clear');
    }
});

// ---------------------------------------------------------------------------
// BH-R9-04 (v1.7.8) — the suite must not leak testing bootstrap caches
// ---------------------------------------------------------------------------

test('the suite guardian removes testing bootstrap caches so dev boots stay clean', function () {
    // Simulate what an in-test pv:update run leaves behind: a config cache
    // with the TESTING environment baked in (sqlite :memory:, array mail).
    File::put(base_path('bootstrap/cache/config.php'), "<?php return ['environment' => 'testing', 'database' => ['default' => 'sqlite']];");
    File::put(base_path('bootstrap/cache/routes-v7.php'), '<?php return [];');

    expect(File::exists(base_path('bootstrap/cache/config.php')))->toBeTrue();

    // The guardian is what Pest.php's afterEach runs for every test; proving
    // it here keeps the cleanup itself from silently rotting.
    removeTestingBootstrapCaches();

    expect(File::exists(base_path('bootstrap/cache/config.php')))->toBeFalse()
        ->and(File::exists(base_path('bootstrap/cache/routes-v7.php')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// H3 — cache rebuild contract (post-deploy hotfix 2)
// ---------------------------------------------------------------------------

test('the update pipeline rebuilds config and clears stale compiled views', function () {
    // H3 (v1.7.3 hotfix 2): on shared cPanel the pipeline is the ONLY thing
    // that refreshes caches — a changed Blade component otherwise keeps
    // serving the previous release's compiled view forever (this is the
    // "my fix didn't show up on the site" class). Lock the ORDER: config is
    // re-cached, then view:clear runs BEFORE view:cache so stale compiled
    // views can never survive into the new cache.
    $source = (string) file_get_contents(app_path('Console/Commands/UpdateFromRelease.php'));

    // config is re-cached through the ['config:cache', 'route:cache'] loop —
    // config:cache REWRITES bootstrap/cache/config.php, so a separate
    // config:clear is unnecessary (and would break the D3 reload below,
    // which reads that file back). What matters is the ORDER.
    expect($source)->toContain("'config:cache'")
        ->and($source)->toContain("Artisan::call('view:clear')")
        ->and($source)->toContain("Artisan::call('view:cache')")
        // view:clear before view:cache, by source position.
        ->and(strpos($source, "Artisan::call('view:clear')"))
        ->toBeLessThan(strpos($source, "Artisan::call('view:cache')"))
        ->and(strpos($source, "'config:cache'"))
        ->toBeLessThan(strpos($source, "Artisan::call('view:clear')"));
});

// ---------------------------------------------------------------------------
// D4 — production seeder gate
// ---------------------------------------------------------------------------

test('demo, bulk and flagship seeders hard-refuse in production', function () {
    // Swap the environment via the seeder's own check: app()->environment()
    // reads the app's env — set it through the instance, not just config.
    app()->detectEnvironment(fn () => 'production');

    Artisan::call('db:seed', ['--force' => true]);
    $output = Artisan::output();

    expect(Prompt::count())->toBe(0, 'zero demo prompts must exist after a production seed')
        ->and(User::where('email', 'like', '%promptsewa.test')->exists())->toBeFalse()
        ->and(User::where('email', 'justshipitai@gmail.com')->exists())->toBeFalse()
        ->and($output)->toContain('REFUSED');

    // Marker seeders still run (none exist beyond tool logos/settings —
    // tool logos are cosmetic and remain allowed).
});

test('local env seeding is unchanged', function () {
    app()->detectEnvironment(fn () => 'local');

    Artisan::call('db:seed', ['--force' => true]);
    $output = Artisan::output();

    expect(Prompt::count())->toBeGreaterThan(0)
        ->and($output)->not->toContain('REFUSED');
});

// ---------------------------------------------------------------------------
// D5 — pv:purge-demo command
// ---------------------------------------------------------------------------

test('pv:purge-demo dry run writes nothing; force hard-deletes unused and bans linked', function () {
    $unused = User::factory()->create(['email' => 'dorje@promptsewa.test', 'role' => User::ROLE_CREATOR]);
    $linked = User::factory()->create(['email' => 'justshipitai@gmail.com', 'role' => User::ROLE_CREATOR]);

    // Give the linked account money-adjacent rows.
    $order = App\Models\Order::factory()->for($linked, 'buyer')->create();

    // Dry run: reports but writes nothing.
    Artisan::call('pv:purge-demo', ['--dry-run' => true]);
    expect(User::find($unused->id))->not->toBeNull()
        ->and(User::find($linked->id))->not->toBeNull()
        ->and($linked->refresh()->banned_at)->toBeNull();

    // Force: unused hard-deletes, money-linked bans+renames.
    Artisan::call('pv:purge-demo', ['--force' => true]);

    expect(User::find($unused->id))->toBeNull('unused demo identity must hard-delete')
        ->and(User::find($linked->id))->not->toBeNull('money-linked identity must survive')
        ->and($linked->refresh()->banned_at)->not->toBeNull()
        ->and($linked->username)->toStartWith('removed-demo-')
        ->and($order->refresh()->buyer_id)->toBe($linked->id, 'financial rows are never touched');
});
