<?php

use App\Models\SikkaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * S7 (v1.8.0) battery, seeded with S2 — Sikka ledger immutability.
 *
 * Three layers (the wallet guard's mirror):
 *   1. model boot: ->save() on an existing row / ->delete() throws;
 *   2. repo-wide arch scan: no ->update(/->delete( call sites on the model;
 *   3. the UNIQUE idempotency key + transactional contract tests in
 *      SikkaServiceTest (S2).
 */
function sikkaLedgerRow(): SikkaTransaction
{
    $user = User::factory()->create();

    return SikkaTransaction::query()->create([
        'user_id' => $user->id,
        'type' => SikkaTransaction::TYPE_ADMIN_GRANT,
        'amount_sikka' => 100,
        'cashout_eligible' => false,
        'idempotency_key' => 'grant:immutability-'.uniqid('', true),
        'meta' => ['reason' => 'immutability test'],
        'created_at' => now(),
    ]);
}

test('updating a sikka transaction throws', function () {
    $row = sikkaLedgerRow();

    $row->amount_sikka = 9999;
    $row->save();
})->throws(RuntimeException::class);

test('deleting a sikka transaction throws', function () {
    $row = sikkaLedgerRow();

    $row->delete();
})->throws(RuntimeException::class);

test('no code path calls update or delete on SikkaTransaction repo-wide', function () {
    $violations = [];

    $root = (string) realpath(__DIR__.'/../../app');

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        $content = (string) file_get_contents($file->getPathname());
        $relative = str_replace('\\', '/', substr((string) $file->getPathname(), strlen($root) + 1));

        if (! str_contains($content, 'SikkaTransaction')) {
            continue;
        }

        if ($relative === 'Models/SikkaTransaction.php') {
            continue; // the guard itself
        }

        foreach (['->update(', '->delete()', 'updateOrCreate(', '->decrement(', '->increment('] as $call) {
            if (str_contains($content, $call)) {
                $violations[] = "{$relative}: '{$call}' appears in a file touching SikkaTransaction — ledger rows are insert-only";
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
