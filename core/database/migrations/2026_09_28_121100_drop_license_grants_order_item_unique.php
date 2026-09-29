<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * v1.4.3 follow-up to 121000: on SQLite, 121000 never dropped the unique
 * index on order_item_id — the drop was compiled away because the
 * `->change()` table rebuild inside the same migration run had already
 * removed it, leaving `if (! $isSqlite) dropUnique` to skip SQLite
 * entirely. On some SQLite histories the index therefore SURVIVES 121000,
 * which forbids pack fulfillment from issuing more than one grant per
 * order line (UNIQUE constraint failed: license_grants.order_item_id).
 *
 * This migration is append-only and guarded: it drops the index on BOTH
 * engines whenever it is still present, regardless of whether 121000 has
 * been recorded. MySQL keeps 121000's own drop; this is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $inspector = app(SchemaInspector::class);

        if (! Schema::hasTable('license_grants')) {
            return;
        }

        if ($inspector->hasUniqueIndex('license_grants', ['order_item_id'])) {
            Schema::table('license_grants', function ($table) {
                $table->dropUnique(['order_item_id']);
            });
        }
    }

    public function down(): void
    {
        $inspector = app(SchemaInspector::class);

        if ($inspector->hasUniqueIndex('license_grants', ['order_item_id'])) {
            return; // nothing to restore
        }

        // Only restore on engines where 121000's down() also restores it.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('license_grants', function ($table) {
                $table->unique('order_item_id');
            });
        }
    }
};
