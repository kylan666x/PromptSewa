<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Admin-panel one-click updater.
 *
 * Accepts a release zip from the dashboard, stores it, and runs the shared
 * pv:update pipeline (extract → migrate → caches → asset sync) in-process.
 * The admin session is the only credential — nothing is exposed over HTTP.
 */
class ReleaseUpdateController extends Controller
{
    public function form(Request $request)
    {
        abort_unless($request->user()?->isModerator(), 403);

        return view('dashboard.update', [
            'zipLimit' => min(
                $this->iniBytes('upload_max_filesize'),
                $this->iniBytes('post_max_size')
            ),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isModerator(), 403);

        $request->validate([
            'release_zip' => ['required', 'file', 'max:102400'], // 100 MB cap
        ]);

        // Guard against hosts whose post_max_size silently swallows the
        // multipart body: both POST fields AND files arrive empty then.
        $len = (int) $request->header('Content-Length', '0');
        if ($len === 0) {
            return back()->withErrors([
                'release_zip' => 'The upload arrived empty — the file likely exceeds the host\'s post_max_size ('.ini_get('post_max_size').'). Raise it in MultiPHP INI Editor, or extract the zip via cPanel and run the direct update.php page.',
            ]);
        }

        // Keep a record of what was applied, then hand off to the exact
        // same pipeline used by update.php / CI-CD.
        $zip = $request->file('release_zip');
        $stored = $zip->storeAs('releases', 'promptsewa-upload-'.now()->format('Ymd-His').'.zip');
        $zipPath = storage_path('app/'.$stored);
        if (! is_file($zipPath)) {
            // Laravel 11+ local disk root includes /private.
            $zipPath = storage_path('app/private/'.$stored);
        }

        $log = [];
        $failed = false;

        try {
            $exit = Artisan::call('pv:update', ['--zip' => $zipPath]);
            $output = trim(Artisan::output());

            foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $level = str_contains($line, '✓') || str_contains($line, 'complete') ? 'ok'
                    : (str_starts_with($line, '  ') && str_contains($line, 'failed') ? 'warn' : 'info');
                $log[] = ['level' => $level, 'line' => $line];
            }

            $failed = $exit !== 0;
        } catch (Throwable $e) {
            report($e);
            $log[] = ['level' => 'bad', 'line' => $e->getMessage()];
            $failed = true;
        }

        return view('dashboard.update', [
            'zipLimit' => min($this->iniBytes('upload_max_filesize'), $this->iniBytes('post_max_size')),
            'log' => collect($log),
            'failed' => $failed,
        ]);
    }

    /** Convert an ini byte-shorthand (e.g. "128M") to raw bytes. */
    private function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        if ($v === '' || $v === '-1') {
            return PHP_INT_MAX;
        }

        return (int) $v * match (strtolower(substr($v, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
    }
}
