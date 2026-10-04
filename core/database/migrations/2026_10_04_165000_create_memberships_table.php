<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — membership instances (state machine: active → expired |
 * cancelled). Expiry flips the membership row ONLY — ledgers are never
 * rewritten (insert-only, invariant #2).
 *
 * source_order_id ties the instance back to the NPR-rail purchase that
 * created it; the approval path is idempotent on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // active | expired | cancelled
            $table->string('status', 20)->default('active');
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->timestamps();

            // The two hot reads: "does this user have an active membership?"
            // and the daily expiry/stipend sweep.
            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
