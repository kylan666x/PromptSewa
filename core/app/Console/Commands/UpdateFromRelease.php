<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
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
            Artisan::call('migrate', ['--force' => true]);
            $this->line('  migrate ✓ '.trim(Artisan::output()));

            foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
                try {
                    Artisan::call($cmd);
                    $this->line("  $cmd ✓");
                } catch (Throwable $e) {
                    $this->warn("  $cmd failed: ".$e->getMessage());
                }
            }

            $this->syncAssets($core, $docroot);

            try {
                Artisan::call('storage:link');
            } catch (Throwable) {
                // cosmetic only
            }
        } finally {
            Artisan::call('up');
            $this->line('  maintenance mode OFF — site is live');
        }

        $this->info('Update complete.');

        return self::SUCCESS;
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
