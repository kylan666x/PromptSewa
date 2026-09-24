<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `prompts` table is the marketplace listing; `prompt_versions` holds
 * the immutable content snapshots (PRD §3.3: git-like version control).
 *
 * cPanel note: semantic search is out of scope on cheap shared hosting —
 * we index a `search_text` column for MySQL/MariaDB FULLTEXT MATCH() and
 * degrade gracefully to LIKE on SQLite during local development/tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();

            // --- Ownership & taxonomy --------------------------------------
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // creator
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete(); // category may be removed; listing survives

            // --- Forking lineage (PRD §3.3) --------------------------------
            // NULL = original work; set = forked from this prompt.
            $table->foreignId('forked_from_prompt_id')
                ->nullable()
                ->constrained('prompts')
                ->nullOnDelete();

            // --- Marketplace fields (PRD §3.1) ------------------------------
            $table->string('title', 160);
            $table->string('slug', 180)->unique(); // /prompts/{slug} — unique for pretty cPanel URLs
            $table->text('description');
            $table->string('search_text', 4000); // title+description+tags concatenated for MATCH()
            $table->string('license_tier', 20)->default('personal'); // personal|commercial
            $table->unsignedInteger('price_cents')->default(0); // minor units; 0 = free listing

            // --- Denormalized counters (avoid COUNT(*) on every list page) --
            $table->unsignedBigInteger('download_count')->default(0)->index();
            $table->unsignedBigInteger('fork_count')->default(0);
            $table->unsignedInteger('upvotes')->default(0)->index();
            $table->unsignedInteger('downvotes')->default(0);

            // --- State machine ---------------------------------------------
            $table->string('status', 20)->default('draft')->index(); // draft|pending|published|rejected

            $table->timestamps();
            $table->softDeletes();

            // --- Indexes tuned to actual marketplace queries ----------------
            $table->index(['status', 'created_at']);          // "newest published" feed
            $table->index(['category_id', 'status']);         // browse by category
            $table->index(['user_id', 'status']);             // creator dashboard

            // MySQL/MariaDB FULLTEXT for MATCH() semantic-ish search (PRD §4).
            // SQLite (local dev/tests) does not support FULLTEXT — skip it there;
            // the Prompt::scopeSearch() scope falls back to LIKE on SQLite.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['search_text']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
