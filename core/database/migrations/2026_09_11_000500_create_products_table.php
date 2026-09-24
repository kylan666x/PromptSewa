<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-001: products — the sellable offer, 1-to-1 with prompts.
 *
 * Financial note: every monetary column is BIGINT paisa (1 NPR = 100
 * paisa). No decimals, no floats — Chief Architect directive, Sprint 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // 1-to-1 with prompts; deleting a prompt must not silently
            // destroy financial history → RESTRICT (no cascade).
            $table->foreignId('prompt_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('price_paisa'); // 1 NPR = 100 paisa
            $table->unsignedBigInteger('compare_at_price_paisa')->nullable(); // "was" price for display
            $table->string('status', 20)->default('draft')->index(); // draft|active|archived
            $table->string('currency', 3)->default('NPR'); // ISO 4217
            $table->timestamps();

            // Only one ACTIVE offer per product-market pairing.
            $table->index(['status', 'price_paisa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
