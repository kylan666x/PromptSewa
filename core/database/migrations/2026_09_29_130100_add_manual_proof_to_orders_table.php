<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 (v1.4.4): buyer-submitted manual payment proof on orders.
 *
 * manual_txn_id     — transaction ID the buyer pasted (≤100 chars).
 * manual_proof_path — screenshot path on the PRIVATE proofs disk; served
 *                     through an owner/staff controller route, never a
 *                     raw public storage URL (payment screenshots are
 *                     quasi-financial data).
 * manual_submitted_at — when the current proof was (re)submitted.
 *
 * The legacy payment_reference (free-text) stays untouched for history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('manual_txn_id', 100)->nullable()->after('payment_reference');
            $table->string('manual_proof_path')->nullable()->after('manual_txn_id');
            $table->timestamp('manual_submitted_at')->nullable()->after('manual_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['manual_txn_id', 'manual_proof_path', 'manual_submitted_at']);
        });
    }
};
