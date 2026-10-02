<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H1 (v1.7.3 hotfix) — prompt body ceiling 4,000 → 50,000 chars.
 *
 * Validation was the only gate at 4,000 (PromptFormRequest), but the STORAGE
 * needed widening too: the v1.0 create made `prompt_versions.body` a `text`
 * column — 65,535 BYTES on MySQL, so a 50,000-char multibyte body (Nepali
 * Devanagari ≈ 3 bytes/char) would have SILENTLY TRUNCATED in strict mode or
 * clipped in lenient mode. The prod techadda_main column is still TEXT —
 * the 2026_09_29 snapshot migration's change() only ran when the column was
 * already longText on SQLite dev; prod stayed behind.
 *
 * SQLite ignores column lengths (no-op semantics), MySQL takes the direct
 * MODIFY — same dual-path as 2026_09_29_140200.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prompt_versions', 'body')) {
            return; // nothing to widen
        }

        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->longText('body')->nullable()->change();
        });

        // MySQL explicit MODIFY — change() covers SQLite + others; this is
        // the belt-and-suspenders path for the cPanel host.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `prompt_versions` MODIFY `body` LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prompt_versions', 'body')) {
            return;
        }

        // v1.0-era shape (TEXT NULL). Existing >64KB bodies are truncated by
        // MySQL on the way back down — the down() path is for local
        // rollbacks only, never prod.
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `prompt_versions` MODIFY `body` TEXT NULL');
        }
    }
};
