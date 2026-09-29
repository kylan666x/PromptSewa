<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 (v1.4.4): manual payment methods v2.
 *
 * Admin-configured methods (eSewa/Khalti/bank) with per-method
 * instructions and a scannable QR code. Orders snapshot the method NAME
 * as a plain string at checkout — editing or deactivating a method never
 * rewrites order history. Seed nothing: prod configures its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('instructions')->nullable();
            $table->string('qr_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_methods');
    }
};
