<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1b (v1.9.0) — Sikka becomes the membership price of record.
 *
 * Same contract as prompts and packs: price_sikka (integer, 0 = free) is
 * authored from v1.9.0 onward, price_paisa stays the derived NPR mirror
 * (price_sikka × buy rate) for the order snapshot and the settlement desk.
 *
 * Backfill: ceil(price_paisa / buy) with buy = 100, rounding UP so a paid
 * plan can never become free through the migration.
 */
return new class extends Migration
{
    public const BACKFILL_BUY_RATE = 100;

    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedInteger('price_sikka')->default(0);
        });

        $buy = self::BACKFILL_BUY_RATE;

        DB::table('membership_plans')
            ->select('id', 'price_paisa')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($buy): void {
                foreach ($rows as $row) {
                    $paisa = (int) $row->price_paisa;

                    if ($paisa <= 0) {
                        continue;
                    }

                    DB::table('membership_plans')->where('id', $row->id)->update([
                        'price_sikka' => intdiv($paisa + $buy - 1, $buy),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('membership_plans', 'price_sikka')) {
            Schema::table('membership_plans', function (Blueprint $table) {
                $table->dropColumn('price_sikka');
            });
        }
    }
};
