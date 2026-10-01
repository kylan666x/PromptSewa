<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * M6 — commission integer property test: for ANY paisa amount, the creator
 * credit plus the platform share must equal the gross, exactly, with no
 * floats anywhere. 200 random values (including awkward small amounts and
 * bps settings) are the release gate for the "money is sacred" invariant.
 */
test('credit plus platform share equals gross across 200 random paisa values', function () {
    $wallet = app(App\Services\WalletService::class);

    foreach ([2000, 2500, 1750, 5000, 0, 999] as $bps) {
        \App\Models\Setting::query()->updateOrCreate(['key' => 'commission_bps'], ['value' => (string) $bps]);

        for ($i = 0; $i < 200; $i++) {
            $gross = random_int(1, 5_000_000); // Rs. 0.01 … Rs. 50,000

            $credit = $wallet->creatorCreditPaisa($gross);
            $platform = $gross - $credit;

            expect($credit + $platform)->toBe($gross)
                ->and($credit)->toBeInt()
                ->and($platform)->toBeInt()
                ->and($credit)->toBeGreaterThanOrEqual(0)
                ->and($platform)->toBeGreaterThanOrEqual(0)
                ->and($credit)->toBeLessThanOrEqual($gross);

            // The floor property: credit is EXACTLY floor(gross × (10000−bps)/10000).
            expect($credit)->toBe(intdiv($gross * (10000 - $bps), 10000));
        }
    }
});

test('walletservice contains no float casts or float functions', function () {
    $source = file_get_contents(realpath(__DIR__.'/../../app/Services/WalletService.php'));

    // The money path is integer-only: no (float)/(double) casts, no
    // floatval/round, no division that is not intdiv.
    expect(preg_match('/\(float\)|\(double\)|floatval\(|round\(/i', $source))->toBe(0)
        ->and(substr_count($source, 'intdiv('))->toBeGreaterThanOrEqual(1);

    // Any "/" division outside intdiv is banned in the service.
    preg_match_all('~(?<![*])/[^\s/*]~', preg_replace('~/\*.*?\*/|//.*~s', '', $source), $divs);
    expect($divs[0])->toBe([], 'WalletService must not use float-prone division — use intdiv().');
});

test('views never echo raw paisa values', function () {
    $viewDir = realpath(__DIR__.'/../../resources/views');
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace('\\', '/', substr((string) $file->getPathname(), strlen($viewDir) + 1));

        // An echo of a *paisa variable* must go through money_npr. Variables
        // named *_paisa (or price_cents, the legacy name) echoed without the
        // helper are the banned pattern.
        if (preg_match_all('/\{\{\s*\$[a-zA-Z_]*(paisa|price_cents)[a-zA-Z_]*\s*\}\}/', $content, $m)) {
            $violations[] = $relative.': raw paisa echo '.implode(', ', $m[0]).' — wrap in money_npr()';
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
