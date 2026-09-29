<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * pv:update — shared update pipeline.
 *
 * Used by:
 *  - the admin panel (ReleaseUpdateController): uploads land in storage,
 *    then this command extracts + migrates + caches + syncs assets;
 *  - CI/CD and power users: `php artisan pv:update --zip=/path/release.zip`.
 *
 * The standalone docroot updater (update.php) keeps its own copy of the
 * same steps for hosts where the admin panel isn't reachable — the two
 * must stay behaviorally identical.
 */
class UpdateFromRelease extends Command
{
    protected $signature = 'pv:update
        {--zip= : Path to a release zip (core/ + public_html/ layout) to extract before updating}
        {--no-extract : Skip zip extraction (run migrate/caches/assets only)}';

    protected $description = 'Extract a release zip (optional), run migrations, rebuild caches and re-sync assets';

    public function handle(): int
    {
        $core = base_path();
        $docroot = $this->docroot($core);

        $this->info('PromptSewa update — core: '.$core);

        if (! is_file($core.'/.env') || ! preg_match('/^APP_KEY=base64:.+$/m', (string) file_get_contents($core.'/.env'))) {
            $this->error('.env is missing or has no APP_KEY — run install.php first (fresh installs only).');

            return self::FAILURE;
        }

        // One-time rebrand: older installs carry APP_NAME=PromptSewa (or
        // the framework default). The site name must read PromptSewa.
        $envPath = $core.DIRECTORY_SEPARATOR.'.env';
        $envContents = (string) file_get_contents($envPath);
        $rebranded = @file_put_contents(
            $envPath,
            preg_replace('/^APP_NAME=.*$/m', 'APP_NAME=PromptSewa', $envContents)
        );
        if ($rebranded !== false && $envContents !== file_get_contents($envPath)) {
            $this->line('  APP_NAME rebranded to PromptSewa in .env');
        }

        $zipPath = $this->option('zip');

        if (! $this->option('no-extract')) {
            if ($zipPath === null) {
                $this->error('Provide --zip=/path/to/release.zip or --no-extract.');

                return self::FAILURE;
            }
            $this->extractRelease($zipPath, $core, $docroot);
        }

        Artisan::call('down');
        $this->line('  maintenance mode ON');

        try {
            $this->runPipeline($core, $docroot);
        } catch (Throwable $e) {
            // R3: on a fatal, the site STAYS in maintenance mode. MySQL DDL
            // autocommits, so the schema may be half-migrated — serving
            // traffic on a half-state is worse than a deliberate outage.
            // Ops copy in the failure screen + update-failed.json tell the
            // admin exactly how to recover.
            $this->writeFailureRecord($e, $core);
            $this->error('FATAL: '.$e->getMessage());
            $this->line('  maintenance mode LEFT ON — see '.str_replace($core.DIRECTORY_SEPARATOR, '', $core).'/storage/logs/update-failed.json for the recovery checklist.');

            throw $e;
        }

        Artisan::call('up');
        $this->line('  maintenance mode OFF — site is live');

        $this->info('Update complete.');

        return self::SUCCESS;
    }

    private function runPipeline(string $core, string $docroot): void
    {
        // D2 (v1.4.5) step 0: purge bootstrap caches before anything runs.
        // The admin-panel path boots from the host's own cache, but a cache
        // that rode in on a zip must never survive the pipeline either.
        $purged = $this->purgeBootstrapCaches($core);
        $this->line('  bootstrap caches purged ('.($purged === [] ? 'nothing to purge' : implode(', ', $purged)).')');

        Artisan::call('migrate', ['--force' => true]);
        $this->line('  migrate ✓ '.trim(Artisan::output()));

        // Idempotent seeders: DemoContent/BulkCatalog/JustShipIt all guard
        // on existing rows, so this is a no-op for installs that already
        // have the catalog and fills in new content on upgrades. Demo/
        // bulk seeders additionally HARD-REFUSE in production (D4).
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $this->line('  seed ✓ '.trim(Artisan::output()));
        } catch (Throwable $e) {
            $this->warn('  seed failed: '.$e->getMessage());
        }

        // view:clear first: stale compiled views from the previous
        // release must never survive into the new view:cache (the
        // v1.4.1 prod 500 came from a drifted compiled view).
        foreach (['config:cache', 'route:cache'] as $cmd) {
            try {
                Artisan::call($cmd);
                $this->line("  $cmd ✓");
            } catch (Throwable $e) {
                $this->warn("  $cmd failed: ".$e->getMessage());
            }
        }

        // D3 (v1.4.5): reload the in-memory repository from the freshly
        // written bootstrap/cache/config.php. config:cache writes the
        // FILE but the running process keeps its boot-time values — on a
        // host whose boot cache was poisoned (or after a purge), later
        // in-process steps (route:cache, view:cache, seeds) would otherwise
        // keep acting on dev paths. Re-reading the canonical cache makes
        // the rest of the pipeline see host config.
        $this->reloadConfigFromCache($core);

        try {
            Artisan::call('view:clear');
            Artisan::call('view:cache');
            $this->line('  view:clear + view:cache ✓');
        } catch (Throwable $e) {
            $this->warn('  view cache failed: '.$e->getMessage());
        }

        $this->syncAssets($core, $docroot);

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // cosmetic only
        }
    }

    /**
     * D2: delete host-local bootstrap cache artifacts (plain unlink). The
     * docroot update.php does the same pre-boot; this covers the
     * admin-panel path post-boot.
     *
     * @return list<string>
     */
    private function purgeBootstrapCaches(string $core): array
    {
        $purged = [];
        $cacheDir = $core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache';

        foreach (['config.php', 'routes.php', 'routes-v7.php', 'packages.php', 'services.php'] as $candidate) {
            $path = $cacheDir.DIRECTORY_SEPARATOR.$candidate;
            if (is_file($path) && @unlink($path)) {
                $purged[] = $candidate;
            }
        }

        return $purged;
    }

    /**
     * D3: reload the in-memory config repository from the freshly written
     * bootstrap/cache/config.php so later in-process steps see host paths.
     */
    private function reloadConfigFromCache(string $core): void
    {
        $cached = $core.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';

        if (! is_file($cached)) {
            $this->warn('  config reload skipped — bootstrap/cache/config.php not found');

            return;
        }

        try {
            $items = require $cached;

            if (is_array($items)) {
                config()->set($items);
                $this->line('  in-memory config reloaded from cache (host paths)');
            }
        } catch (Throwable $e) {
            $this->warn('  config reload failed: '.$e->getMessage());
        }
    }

    /**
     * Persist an ops-facing failure record next to laravel.log. The admin
     * failure screen (and update.php) read this — it survives the request
     * that crashed and names the exact recovery steps.
     */
    private function writeFailureRecord(Throwable $e, string $core): void
    {
        $record = [
            'failed_at' => now()->toIso8601String(),
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'sql_state_hint' => $this->sqlStateHint($e->getMessage()),
            'maintenance' => 'ON — the site is intentionally still down until an operator completes recovery',
            'recovery_steps' => [
                'Inspect the schema: the failing migration may have partially applied (MySQL DDL autocommits) — verify what actually exists before doing anything.',
                'Do NOT simply re-run the update: if the schema is in a half-state, re-running can fail differently or corrupt data.',
                'When the schema is verified consistent, bring the site back: delete core/storage/framework/down (or run `php artisan up`).',
                'Update zips NEVER ship vendor/ — do not hunt for a missing vendor directory; the failure is schema or code, not the upload.',
            ],
        ];

        try {
            @file_put_contents(
                $core.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'update-failed.json',
                json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable) {
            // never mask the original failure with a logging failure
        }
    }

    /** Map the common SQLSTATE codes an interrupted migration produces to an ops hint. */
    private function sqlStateHint(string $message): ?string
    {
        return match (true) {
            str_contains($message, '1091') => 'SQLSTATE 1091: a DROP targeted an object that does not exist — the schema is likely already (half-)migrated; verify with SHOW INDEX / SHOW COLUMNS before re-running.',
            str_contains($message, '1062') => 'SQLSTATE 1062: duplicate entry — data violates a constraint the migration expects unique; inspect the offending rows before re-running.',
            str_contains($message, '42S21') => 'SQLSTATE 42S21: duplicate column name — the column already exists; the migration partially applied earlier.',
            default => null,
        };
    }

    /** Extract the release zip over core/ + docroot, preserving live dotfiles. */
    private function extractRelease(string $zipPath, string $core, string $docroot): void
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException("Release zip not found: $zipPath");
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ext-zip is missing — extract the release manually, then run with --no-extract.');
        }

        $zip = new ZipArchive();
        $stat = $zip->open($zipPath);
        if ($stat !== true) {
            throw new RuntimeException("Could not open the zip (code $stat).");
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || (! str_starts_with($name, 'core/') && ! str_starts_with($name, 'public_html/'))) {
                $zip->close();
                throw new RuntimeException("Rejected: zip entry '$name' is outside the release layout.");
            }
        }

        $staging = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pv-update-'.bin2hex(random_bytes(4));
        if (! @mkdir($staging, 0755, true)) {
            $zip->close();
            throw new RuntimeException('Could not create a staging directory.');
        }

        try {
            if (! $zip->extractTo($staging)) {
                throw new RuntimeException('Zip extraction failed — check disk quota/permissions.');
            }
            $zip->close();
            $this->line('  zip extracted to staging');

            $this->copyTree($staging.'/core', $core);
            $this->line('  core/ merged');

            $this->copyTree($staging.'/public_html', $docroot);
            $this->line('  public_html/ merged into '.$docroot);
        } finally {
            $this->removeTree($staging);
        }
    }

    /** Docroot = sibling public_html/ (release layout) or core/public fallback. */
    private function docroot(string $core): string
    {
        $sibling = dirname($core).DIRECTORY_SEPARATOR.'public_html';

        return is_dir($sibling) ? $sibling : $core.DIRECTORY_SEPARATOR.'public';
    }

    private function syncAssets(string $core, string $docroot): void
    {
        $src = realpath($core.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build');
        $dst = realpath($docroot).DIRECTORY_SEPARATOR.'build';

        // Local/dev layout (no sibling public_html): assets already live in
        // core/public/build — nothing to sync.
        if ($src !== false && $dst !== false && realpath($src) === realpath($dst)) {
            $this->line('  build assets already in place');

            return;
        }

        if ($src === false || $dst === false || ! is_dir($src)) {
            $this->warn('  core/public/build not found — release shipped without built assets.');

            return;
        }

        if (is_dir($dst)) {
            $this->removeTree($dst);
        }
        $this->copyTree($src, $dst);
        $this->line('  build assets synced to '.basename($docroot).'/build');
    }

    private function copyTree(string $src, string $dst): void
    {
        if (! is_dir($src)) {
            throw new RuntimeException("Missing expected directory: $src");
        }
        if (! is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        foreach (scandir($src) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $s = $src.DIRECTORY_SEPARATOR.$item;
            $d = $dst.DIRECTORY_SEPARATOR.$item;
            if (is_dir($s)) {
                $this->copyTree($s, $d);
            } else {
                @copy($s, $d);
            }
        }
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
