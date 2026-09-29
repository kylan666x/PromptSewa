<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T3 (v1.5.0): admin account-switching session log.
 *
 * One row per impersonation session. `ended_at` is updated when the admin
 * returns — this is a session log, NOT a financial ledger, so updates are
 * allowed (money invariants untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impersonator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['impersonator_id', 'ended_at']);
            $table->index('target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
