<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 (v1.7.8) — the buyer's proof note gets its own column (mediator ruling).
 *
 * Before this migration, a non-empty `note` on the proof form OVERWROTE
 * `payment_reference`, so the admin desk lost the Method · Reference line
 * (the reference is the order's historical record; editing the method later
 * must never rewrite it — locked by ManualPaymentMethodsTest).
 *
 * Additive only: one nullable column, no drops, no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('manual_note', 500)->nullable()->after('manual_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('manual_note');
        });
    }
};
