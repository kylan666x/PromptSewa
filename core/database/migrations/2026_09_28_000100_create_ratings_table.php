<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community ratings (1-5 stars).
 *
 * Policy (enforced in RatingPolicy + controllers):
 *  - Paid prompts: only users holding an active license grant may rate.
 *  - Free prompts: any logged-in user may rate.
 *  - One rating per user per prompt (upsert on re-rate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1..5
            $table->timestamps();

            $table->unique(['user_id', 'prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
