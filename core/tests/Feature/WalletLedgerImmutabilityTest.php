<?php

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * M6 — ledger immutability, enforced at THREE layers:
 *   1. model boot: ->save() on an existing row / ->delete() throws;
 *   2. repo-wide arch scan: no ->update(/->delete( call sites on the model;
 *   3. (indirect) the UNIQUE idempotency key + insert-only contract tests
 *      in WalletServiceTest.
 */

function ledgerRow(): WalletTransaction
{
    $user = User::factory()->create();

    return WalletTransaction::query()->create([
        'user_id' => $user->id,
        'type' => WalletTransaction::TYPE_ADJUSTMENT,
        'amount_paisa' => 1000,
        'idempotency_key' => 'adjustment:immutability-'.uniqid(),
        'meta' => ['reason' => 'immutability test'],
        'created_at' => now(),
    ]);
}

test('updating a wallet transaction throws', function () {
    $row = ledgerRow();

    $row->amount_paisa = 9999;
    $row->save();
})->throws(RuntimeException::class);

test('deleting a wallet transaction throws', function () {
    $row = ledgerRow();

    $row->delete();
})->throws(RuntimeException::class);

test('deleting a payout throws', function () {
    // Payouts are financial records too (AGENTS.md #5).
    $user = User::factory()->create();
    $payout = \App\Models\Payout::query()->create([
        'user_id' => $user->id,
        'amount_paisa' => 5000,
        'status' => \App\Models\Payout::STATUS_REQUESTED,
        'method' => \App\Models\Payout::METHOD_ESEWA_WALLET,
        'destination_encrypted' => 'x',
        'requested_at' => now(),
    ]);

    $payout->delete();
})->throws(RuntimeException::class);

test('no code path calls update or delete on WalletTransaction repo-wide', function () {
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(realpath(__DIR__.'/../../app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace('\\', '/', substr((string) $file->getPathname(), strlen(realpath(__DIR__.'/../../app')) + 1));

        // Cheap-and-broad scan: any ->update( / ->delete( / updateOrCreate(
        // mentioning WalletTransaction in the same file is flagged for
        // manual review. The model itself (boot guards) is exempt by name.
        $mentionsWallet = str_contains($content, 'WalletTransaction');
        if (! $mentionsWallet) {
            continue;
        }

        if ($relative === 'Models/WalletTransaction.php') {
            continue; // the guard itself
        }

        foreach (['->update(', '->delete()', 'updateOrCreate(', '->decrement(', '->increment('] as $call) {
            if (str_contains($content, $call)) {
                $violations[] = "{$relative}: '{$call}' appears in a file touching WalletTransaction — ledger rows are insert-only";
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

test('settleorder has exactly two controller call sites', function () {
    $callSites = [];

    $dirs = [realpath(__DIR__.'/../../app/Http/Controllers')];
    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if (preg_match('/settleOrder\s*\(/', $content) === 1) {
                $callSites[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    // Exactly the two sanctioned paths (M2 contract): the manual-approval
    // controller (Admin/OrderAdminController) and the eSewa verification
    // path (CheckoutController::esewaVerify/esewaWebhook). A THIRD call
    // site is a contract violation.
    sort($callSites);

    $expected = [
        str_replace('\\', '/', realpath(__DIR__.'/../../app/Http/Controllers/Admin/OrderAdminController.php')),
        str_replace('\\', '/', realpath(__DIR__.'/../../app/Http/Controllers/CheckoutController.php')),
    ];

    expect($callSites)->toBe($expected, 'settleOrder has exactly two sanctioned call sites (manual approval + eSewa path) — found: '.implode(', ', $callSites));
});
