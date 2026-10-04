<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — Sikka cash-outs ride the EXISTING payouts state machine.
 *
 * source_currency: 'npr' (legacy) | 'sikka'. Legacy rows keep their
 * amount_paisa semantics untouched — the NPR rail is byte-identical.
 *
 * For sikka-source payouts:
 *   - sikka_amount carries the held Sikka (the Sikka ledger's payout_hold
 *     row is the real debit; amount_paisa stays 0 — never a second truth);
 *   - settled_npr_paisa is computed ONCE at settle:
 *     sikka_amount × sikka_cashout_paisa_per_token — pure integer multiply.
 *
 * Nothing here rewrites legacy rows beyond the source_currency default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('source_currency', 10)->default('npr');
            $table->unsignedBigInteger('sikka_amount')->nullable();
            $table->unsignedBigInteger('settled_npr_paisa')->nullable();
        });

        DB::table('payouts')->whereNull('sikka_amount')->update(['sikka_amount' => 0]);
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['source_currency', 'sikka_amount', 'settled_npr_paisa']);
        });
    }
};
