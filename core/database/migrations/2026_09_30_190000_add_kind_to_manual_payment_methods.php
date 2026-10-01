<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3 (v1.7.1) — manual_payment_methods.kind.
 *
 * bank | esewa | other — drives the checkout per-method card's kind icon
 * (bank glyph / eSewa wordmark / generic mono glyph). Append-only: the
 * column is added with a default of 'other', which backfills every
 * existing row — no history is rewritten, no nullable lie is introduced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payment_methods', function (Blueprint $table) {
            $table->string('kind', 20)->default('other')->after('name');
        });

        // Belt-and-braces for engines that do not backfill on ADD COLUMN
        // (the default already does this on MySQL/SQLite).
        DB::table('manual_payment_methods')
            ->whereNotIn('kind', ['bank', 'esewa', 'other'])
            ->update(['kind' => 'other']);
    }

    public function down(): void
    {
        Schema::table('manual_payment_methods', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
