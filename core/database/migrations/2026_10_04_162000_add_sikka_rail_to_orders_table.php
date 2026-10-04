<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — the Sikka checkout rail rides ON the existing orders table.
 *
 * currency becomes the rail marker: 'npr' (legacy) | 'sikka'. The column
 * was varchar(3) carrying the uppercase 'NPR' string — 'sikka' needs more
 * room, so it widens to varchar(10) and existing rows are normalized to
 * the canonical lowercase marker.
 *
 * sikka_amount is the Sikka price actually charged on the sikka rail
 * (integer, no decimals). NPR-rail rows backfill to 0 per the contract.
 * The buy-rate snapshot lives in the order meta written at purchase time —
 * history never re-prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel 12 change() re-declares the full column: varchar(10),
        // NOT NULL, default 'npr' (the pre-existing default was 'NPR').
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 10)->default('npr')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            // Nullable: legacy rows are backfilled to 0 below, but a null
            // never means "free" anywhere — readers default it to 0.
            $table->unsignedInteger('sikka_amount')->nullable();
            // Purchase-time snapshot bag (buy rate at purchase — history
            // never re-prices). Not financial data: nothing derives from it.
            $table->json('meta')->nullable();
        });

        DB::table('orders')->where('currency', 'NPR')->update(['currency' => 'npr']);
        DB::table('orders')->whereNull('sikka_amount')->update(['sikka_amount' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'sikka_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('sikka_amount');
            });
        }

        if (Schema::hasColumn('orders', 'meta')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('NPR')->change();
        });
    }
};
