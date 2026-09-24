<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-001: order_items — line items snapshotting what was bought and at
 * which price, so later price changes never rewrite financial history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                ->constrained()
                ->restrictOnDelete(); // never orphan financial lines
            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('prompt_id')
                ->constrained()
                ->restrictOnDelete();
            // Price AT PURCHASE TIME (snapshot), independent of the
            // product's current price.
            $table->unsignedBigInteger('price_paisa');
            $table->string('currency', 3)->default('NPR');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
