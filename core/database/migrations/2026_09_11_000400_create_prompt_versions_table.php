<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable content snapshots — the "commits" of a prompt's history (PRD §3.3).
 *
 * Never update or delete rows here: fork diffing and pull-request reviews
 * depend on history being append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();

            // Monotonic per-prompt version number: 1, 2, 3... (v-prefix in UI).
            $table->unsignedInteger('version_number');

            // Content snapshot
            $table->text('body');            // the prompt itself
            $table->text('changelog')->nullable(); // "what changed & why"
            $table->json('variables')->nullable(); // {{placeholders}} extracted by the playground
            $table->json('tags')->nullable();

            // The user who authored this specific version (may differ from
            // prompt owner once pull-requests are merged).
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Pull-request style review state for non-owner contributions.
            $table->string('status', 20)->default('published')->index(); // draft|pending|published|rejected

            $table->timestamps();

            // One row per (prompt, number) — enforced, not just assumed.
            $table->unique(['prompt_id', 'version_number']);
            // "Latest versions" tab: walk versions of a prompt newest-first.
            $table->index(['prompt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_versions');
    }
};
