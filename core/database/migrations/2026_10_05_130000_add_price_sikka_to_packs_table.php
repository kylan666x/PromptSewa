<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1b (v1.9.0) — Sikka becomes the pack price of record.
 *
 * Mirrors the prompts migration (2026_10_04_161000): price_sikka (integer,
 * 0 = free) is authored from v1.9.0 onward; price_paisa — the legacy NPR
 * column the ORDER SNAPSHOT math still reads — is retained as the derived
 * mirror (price_sikka × buy rate) so history and the parity replay stay
 * byte-identical.
 *
 * Backfill: ceil(price_paisa / buy) with buy = 100 (the default buy rate,
 * one Sikka = NPR 1 = 100 paisa), rounding UP so a paid pack can never
 * become free through the migration.
 *
 * No `after()` anchor: the v1.4.3 parity replay rebuilds the base schema
 * without later migrations present; column order is cosmetic.
 */
return new class extends Migration
{
    public const BACKFILL_BUY_RATE = 100;

    public function up(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            $table->unsignedInteger('price_sikka')->default(0);
        });

        $buy = self::BACKFILL_BUY_RATE;

        DB::table('packs')
            ->select('id', 'price_paisa')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($buy): void {
                foreach ($rows as $row) {
                    $paisa = (int) $row->price_paisa;

                    if ($paisa <= 0) {
                        continue; // free stays free
                    }

                    DB::table('packs')->where('id', $row->id)->update([
                        'price_sikka' => intdiv($paisa + $buy - 1, $buy),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('packs', 'price_sikka')) {
            Schema::table('packs', function (Blueprint $table) {
                $table->dropColumn('price_sikka');
            });
        }
    }
};
