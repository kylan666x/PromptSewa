<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-001: orders — a purchase attempt with an idempotency key so a
 * double-submitted checkout can never create two financial records.
 *
 * Orders are financial history: no cascade deletes anywhere, rows are
 * never removed (AGENTS.md financial invariant #5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Financial records must survive user deletion for audit → RESTRICT.
            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index(); // pending|paid|failed|refunded
            $table->unsignedBigInteger('subtotal_paisa');
            $table->unsignedBigInteger('tax_paisa')->default(0);
            $table->unsignedBigInteger('total_paisa');
            $table->string('currency', 3)->default('NPR');
            // Checkout idempotency: UNIQUE prevents duplicate orders on
            // concurrent double-submission (acceptance criterion #3).
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
