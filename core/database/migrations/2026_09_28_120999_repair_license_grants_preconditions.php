<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R1 — v1.4.3 "Migration Replay Repair".
 *
 * Prod hit SQLSTATE 1091 replaying 2026_09_28_121000 after an interrupted
 * attempt left partial DDL: MySQL autocommits every DDL statement, so the
 * dropped unique index was GONE while the `migrations` row was never
 * written — the replay then failed dropping an index that no longer existed.
 *
 * Repair rule (append-only: committed migrations are never edited): this
 * back-dated migration (120999, between 120000 and 121000) runs BEFORE
 * 121000 on prod and normalizes the schema to the exact pre-121000
 * precondition so 121000 replays cleanly and records itself.
 *
 * Fully idempotent:
 *  - 121000 already recorded (healthy local DBs) → strict no-op.
 *  - Partial/pre state → drop any comp columns 121000 adds, re-create the
 *    unique index if missing (failing LOUD with a clear message if
 *    duplicate non-null order_item_id values would block re-creation —
 *    normalization must never silently corrupt the idempotency contract).
 */
return new class extends Migration
{
    private const COMPLETION_BATCH = '2026_09_28_121000_make_license_grants_comp_capable';

    /** Columns 121000 adds — must match that migration exactly. */
    private const COMP_COLUMNS = ['issued_by', 'issue_reason'];

    public function up(): void
    {
        $inspector = app(SchemaInspector::class);

        // Healthy post-state (all local DBs, already-updated prod): no-op.
        if ($this->migrationRecorded(self::COMPLETION_BATCH)) {
            return;
        }

        // --- Normalize to the exact pre-121000 precondition ----------------

        // 1. Drop any comp columns a partial 121000 already added.
        foreach (self::COMP_COLUMNS as $column) {
            if ($inspector->hasColumn('license_grants', $column)) {
                Schema::table('license_grants', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // 2. Ensure the unique index on order_item_id exists.
        if (! $inspector->hasUniqueIndex('license_grants', ['order_item_id'])) {
            $duplicates = DB::table('license_grants')
                ->select('order_item_id', DB::raw('COUNT(*) as n'))
                ->whereNotNull('order_item_id')
                ->groupBy('order_item_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            if ($duplicates->isNotEmpty()) {
                // Fail loud rather than corrupt the one-grant-per-line rule.
                throw new RuntimeException(
                    'Repair 120999 cannot re-create license_grants_order_item_id_unique: '
                    .$duplicates->count().' duplicate order_item_id value(s) found '
                    .'(first: '.$duplicates->first()->order_item_id.', count: '.$duplicates->first()->n.'). '
                    .'Resolve the duplicates manually, then re-run php artisan migrate.'
                );
            }

            Schema::table('license_grants', function (Blueprint $table) {
                $table->unique('order_item_id');
            });
        }

        // 3. order_item_id back to NOT NULL (the pre-121000 shape; 121000
        //    makes it nullable again as its own recorded first step).
        if ($this->orderItemColumnAllowsNull()) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                // SQLite cannot ALTER COLUMN — rebuild the table without
                // dropping data (row copy preserves everything).
                $this->rebuildSqliteWithNotNullOrderItem();
            } else {
                DB::statement('ALTER TABLE `license_grants` MODIFY `order_item_id` BIGINT UNSIGNED NOT NULL');
            }
        }
    }

    public function down(): void
    {
        // Irreversible normalization — nothing to undo.
    }

    private function migrationRecorded(string $migration): bool
    {
        return DB::table('migrations')->where('migration', $migration)->exists();
    }

    private function orderItemColumnAllowsNull(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::select("PRAGMA table_info('license_grants')");
            foreach ($row as $column) {
                if ($column->name === 'order_item_id') {
                    return ((int) $column->notnull) === 0;
                }
            }

            return false;
        }

        $row = DB::select(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS "
            ."WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'license_grants' AND COLUMN_NAME = 'order_item_id'"
        );

        return (($row[0]->IS_NULLABLE ?? 'YES') === 'YES');
    }

    /**
     * SQLite table rebuild: create license_grants_new with order_item_id
     * NOT NULL, copy rows, swap. Uses static column names — every column
     * the table actually has at this point in the migration sequence.
     */
    private function rebuildSqliteWithNotNullOrderItem(): void
    {
        DB::statement('
            CREATE TABLE license_grants_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                order_item_id INTEGER NOT NULL,
                prompt_id INTEGER NOT NULL,
                license_tier VARCHAR(20) NOT NULL,
                grant_code VARCHAR(40) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'active\',
                revoked_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (order_item_id) REFERENCES order_items(id),
                FOREIGN KEY (prompt_id) REFERENCES prompts(id)
            )
        ');

        DB::statement('
            INSERT INTO license_grants_new
                (id, user_id, order_item_id, prompt_id, license_tier, grant_code, status, revoked_at, created_at, updated_at)
            SELECT id, user_id, order_item_id, prompt_id, license_tier, grant_code, status, revoked_at, created_at, updated_at
            FROM license_grants
        ');

        DB::statement('DROP TABLE license_grants');
        DB::statement('ALTER TABLE license_grants_new RENAME TO license_grants');

        // Re-create the indexes the rebuild dropped.
        Schema::table('license_grants', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->unique('order_item_id');
        });
    }
};
