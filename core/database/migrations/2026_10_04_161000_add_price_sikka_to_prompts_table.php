<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — Sikka becomes the price of record.
 *
 * price_sikka (integer, 0 = free) is authored from v1.8.0 onward;
 * price_cents — the legacy misnomer that is actually paisa (intdiv($price_cents, 100)
 * has always been NPR) — is retained as the derived NPR mirror so the
 * existing NPR checkout rail stays byte-identical.
 *
 * Backfill: ceil(price_cents / buy) with buy = 100 (the default buy rate,
 * one Sikka = NPR 1 = 100 paisa). Integer rounding UP never lets a paid
 * prompt become free through the migration.
 *
 * No `after()` anchor: the v1.4.3 parity replay rebuilds `prompts` without
 * later migrations present; column order is cosmetic.
 */
return new class extends Migration
{
    public const BACKFILL_BUY_RATE = 100;

    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedInteger('price_sikka')->default(0);
        });

        // Chunked PHP-side math: driver-agnostic integer arithmetic, no
        // DB-specific DIV/FLOOR dialects (the parity replay runs on MySQL,
        // the suite on SQLite).
        $buy = self::BACKFILL_BUY_RATE;

        DB::table('prompts')
            ->select('id', 'price_cents')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($buy): void {
                foreach ($rows as $row) {
                    $paisa = (int) $row->price_cents;

                    if ($paisa <= 0) {
                        continue; // free stays free
                    }

                    DB::table('prompts')->where('id', $row->id)->update([
                        'price_sikka' => intdiv($paisa + $buy - 1, $buy),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('prompts', 'price_sikka')) {
            Schema::table('prompts', function (Blueprint $table) {
                $table->dropColumn('price_sikka');
            });
        }
    }
};
