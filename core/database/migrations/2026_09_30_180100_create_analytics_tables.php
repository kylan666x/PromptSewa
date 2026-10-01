<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G5 (v1.7.0) — analytics schema.
 *
 * Views are STATS, not money: upserts are allowed here (prompt_daily_stats
 * is idempotent per (prompt, day) by UNIQUE key). This is deliberately
 * unlike the wallet ledger, which stays insert-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::create('prompt_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('views')->default(0);

            // One row per prompt per day — the upsert target.
            $table->unique(['prompt_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_daily_stats');

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
