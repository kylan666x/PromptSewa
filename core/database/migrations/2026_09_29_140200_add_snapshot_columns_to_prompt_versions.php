<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T7 (v1.5.0): version truth — full per-version snapshots.
 *
 * Historically prompt_versions held the body but NOT variables/tools, so
 * older edits silently overwrote the prompt's current metadata. This
 * migration adds the missing snapshot columns when absent (guarded via
 * hasColumn — destructive-free) and they are backfilled from each
 * prompt's LATEST version row by pv:backfill-version-snapshots.
 * Older rows stay null: the history UI shows an honest
 * "snapshot not captured before v1.5.0" chip — never fabricated bodies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('prompt_versions', 'variables')) {
                $table->json('variables')->nullable()->after('body');
            }

            if (! Schema::hasColumn('prompt_versions', 'tools')) {
                $table->json('tools')->nullable()->after('variables');
            }

            if (! Schema::hasColumn('prompt_versions', 'body')) {
                $table->longText('body')->nullable()->after('prompt_id');
            } else {
                // The v1.4.5-era original created `body` as text NOT NULL.
                // T7's honesty chip needs it nullable on EVERY driver —
                // Laravel 12 supports change() on SQLite (table rebuild),
                // MySQL takes the direct MODIFY.
                $table->longText('body')->nullable()->change();
            }
        });

        // Historical body was text NOT NULL in the original create; on
        // MySQL widen explicitly (change() above covers SQLite + others).
        if (Schema::hasColumn('prompt_versions', 'body')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `prompt_versions` MODIFY `body` LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('prompt_versions', function (Blueprint $table) {
            foreach (['tools', 'variables'] as $column) {
                if (Schema::hasColumn('prompt_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
