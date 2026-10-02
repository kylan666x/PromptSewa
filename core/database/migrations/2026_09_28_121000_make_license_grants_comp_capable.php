<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A5 comp grants live outside the order pipeline: drop the NOT NULL
        // and the UNIQUE (one-grant-per-line) constraint on order_item_id.
        // Purchase idempotency stays enforced in EntitlementService inside
        // its transaction; comps have their own (user, prompt, active)
        // idempotency check.
        //
        // The unique index only exists on MySQL (live). SQLite builds the
        // table without it, so the drop is attempted on MySQL engines only.
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        // v1.7.5: this migration IS the v1.4.2 prod incident. A driver check
        // is not an existence check: on a replay after an interrupted run
        // (MySQL DDL autocommits, so no `migrations` row is written) the
        // unique index may already be gone -> SQLSTATE 1091, and the audit
        // columns may already exist -> SQLSTATE 1060. The existence checks
        // below are what the guard test now enforces repo-wide; 121100
        // remains the back-dated repair for hosts that already half-applied.
        if (! Schema::hasTable('license_grants')) {
            return;
        }

        $inspector = app(\App\Support\SchemaInspector::class);
        $dropUnique = ! $isSqlite && $inspector->hasUniqueIndex('license_grants', ['order_item_id']);
        $addAudit = ! $inspector->hasColumn('license_grants', 'issued_by');

        Schema::table('license_grants', function (Blueprint $table) use ($dropUnique, $addAudit) {
            $table->foreignId('order_item_id')->nullable()->change();

            if ($dropUnique) {
                $table->dropUnique(['order_item_id']);
            }

            // Audit columns: who issued the comp and why. Null for
            // purchased grants — non-null implies a comp/manual issue.
            if ($addAudit) {
                $table->foreignId('issued_by')->nullable()->after('grant_code');
                $table->string('issue_reason', 500)->nullable()->after('issued_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropColumn(['issued_by', 'issue_reason']);
            $table->foreignId('order_item_id')->nullable(false)->change();
            $table->unique('order_item_id');
        });
    }
};
