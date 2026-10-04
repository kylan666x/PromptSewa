<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — Sikka top-up packs (admin-managed merchandising).
 *
 * A pack is bought through the ordinary NPR rails (manual / eSewa), and on
 * approval SikkaService::topupCredit credits the buyer: sikka_amount plus
 * bonus_sikka as two ledger rows so the bonus is auditable on its own.
 *
 * price_paisa is BIGINT paisa (invariant #1); the buy rate is never stored
 * here — it lives in settings and is snapshotted into order meta at
 * purchase time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikka_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->unsignedInteger('sikka_amount');
            $table->unsignedInteger('bonus_sikka')->default(0);
            $table->unsignedBigInteger('price_paisa');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sikka_packs');
    }
};
