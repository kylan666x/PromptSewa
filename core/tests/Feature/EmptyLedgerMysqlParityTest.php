<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * K5 (v1.6.1) — MySQL parity of the empty-ledger scenario (D10 pattern).
 *
 * SUM semantics differ subtly across engines; the empty-ledger money
 * surfaces must render identically on prod's MySQL. Skips cleanly when
 * no local MySQL is reachable (SQLite-only CI machines), exactly like
 * DeployParityAcceptanceTest.
 */

function emptyLedgerMysqlConfig(): ?array
{
    $host = getenv('DB_PARITY_HOST') ?: '127.0.0.1';
    $user = getenv('DB_PARITY_USER') ?: 'root';
    $pass = getenv('DB_PARITY_PASSWORD') ?: 'root';

    try {
        $pdo = new PDO("mysql:host={$host};port=3306", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);

        return ['host' => $host, 'user' => $user, 'pass' => $pass, 'pdo' => $pdo];
    } catch (Throwable) {
        return null;
    }
}

test('earnings and finance survive an empty ledger on MySQL', function () {
    $mysql = emptyLedgerMysqlConfig();

    if ($mysql === null) {
        $this->markTestSkipped('No local MySQL reachable — parity run skipped (SQLite CI).');
    }

    $database = 'pv_parity_empty_ledger_'.substr(md5(uniqid('', true)), 0, 8);
    $mysql['pdo']->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    try {
        $this->app['config']->set('database.connections.mysql_parity_empty', [
            'driver' => 'mysql',
            'host' => $mysql['host'],
            'port' => 3306,
            'database' => $database,
            'username' => $mysql['user'],
            'password' => $mysql['pass'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        // RefreshDatabase against the parity connection.
        config(['database.default' => 'mysql_parity_empty']);

        $this->artisan('migrate', ['--force' => true])->run();

        // Empty-ledger actors (zero wallet rows — the prod shape).
        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // SUM over zero rows on MySQL returns NULL — the boundary casts
        // must hold exactly as they do on SQLite.
        $wallet = app(\App\Services\WalletService::class);
        expect($wallet->balancePaisa($creator))->toBe(0)
            ->and($wallet->availablePaisa($creator))->toBe(0)
            ->and($wallet->commissionBps())->toBe(2000); // K3: absent row → documented default

        // Full-stack renders.
        $earnings = $this->actingAs($creator)->get(route('dashboard.earnings'));
        expect($earnings->status())->toBe(200, '/earnings must be 200 on MySQL with an empty ledger')
            ->and($earnings->getContent())->toContain('Rs. 0.00');

        $finance = $this->actingAs($admin)->get(route('admin.finance'));
        expect($finance->status())->toBe(200, 'finance desk must be 200 on MySQL with an empty ledger')
            ->and($finance->getContent())->toContain('Rs. 0.00');
    } finally {
        // Teardown: drop the parity DB and restore the default connection.
        $this->app['config']->set('database.default', 'sqlite');
        $mysql['pdo']->exec("DROP DATABASE IF EXISTS `{$database}`");
    }
});
