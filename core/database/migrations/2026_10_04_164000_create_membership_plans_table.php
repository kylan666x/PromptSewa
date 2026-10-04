<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — membership plans (admin-managed, sold on the public
 * /membership page through the NPR rails; activation happens on order
 * approval — grants follow payments, never precede them).
 *
 * perks is a bounded JSON bag:
 *   unlimited_unlock  bool  — buyers with an active membership skip Sikka
 *                             spends entirely (entitlement, never balance)
 *   badge_id          ?int  — badge granted on activation (idempotent)
 *   frame_id          ?int  — frame unlocked on activation (idempotent)
 *   grant_verified    bool  — founder-verified toggle on activation
 *
 * duration_days bounds the membership window; stipend_sikka is credited per
 * elapsed 30-day period by pv:stipend-scan, idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('duration_days');
            $table->unsignedBigInteger('price_paisa');
            $table->unsignedInteger('stipend_sikka')->default(0);
            $table->json('perks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
