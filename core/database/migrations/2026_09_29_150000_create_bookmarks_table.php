<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T13 (v1.5.0): bookmarks — saved prompts per user. Append-only creation;
 * dropping the table in down() is the only destructive operation and it
 * carries no financial data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookmarks')) {
            return;
        }

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'prompt_id']);
            $table->index('prompt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
