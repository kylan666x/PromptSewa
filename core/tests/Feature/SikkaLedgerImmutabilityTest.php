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

        // LEDGER-RECEIVER scan (S6 refinement): the Sikka desk legitimately
        // updates/deletes its PACK/PLAN rows in a file that also READS the
        // ledger, so a file-level string scan flags unrelated CRUD. The ban
        // is therefore exact about the RECEIVER: a mutator is a violation
        // only when the ledger itself is mutated — directly, through the
        // query builder, through the raw table, or through a variable alias
        // of a SikkaTransaction expression.
        if (preg_match('/SikkaTransaction::(?:(?!;).){0,600}?->\s*(update|updateOrCreate|delete|forceDelete|increment|decrement)\s*\(/s', $content, $m)) {
            $violations[] = "{$relative}: '->{$m[1]}(' is called on SikkaTransaction — ledger rows are insert-only";
        }

        if (preg_match('/SikkaTransaction::(destroy|forceDestroy)\s*\(/', $content)) {
            $violations[] = "{$relative}: a static destroy call targets SikkaTransaction — ledger rows are insert-only";
        }

        // The raw table is the same ledger — DB::table mutators are banned too.
        if (str_contains($content, 'sikka_transactions')
            && preg_match('/->\s*(update|updateOrCreate|delete|forceDelete|increment|decrement)\s*\(/', $content)) {
            $violations[] = "{$relative}: a mutator runs in a file touching the sikka_transactions table — ledger rows are insert-only";
        }

        // Alias guard: `$row = SikkaTransaction::…` must never be mutated later.
        if (preg_match_all('/(\$[a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*SikkaTransaction::/', $content, $aliases)) {
            foreach (array_unique($aliases[1]) as $var) {
                if (preg_match('/'.preg_quote($var, '/').'\s*->\s*(update|updateOrCreate|delete|forceDelete|increment|decrement)\s*\(/', $content)) {
                    $violations[] = "{$relative}: alias {$var} of SikkaTransaction is mutated — ledger rows are insert-only";
                }
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
