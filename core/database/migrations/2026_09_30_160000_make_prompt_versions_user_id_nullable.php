<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H2 (v1.5.2) — version authorship resilience.
 *
 * prompt_versions.user_id was created with `constrained()->cascadeOnDelete()`.
 * After the v1.5.2 catalog adoption, the ORIGINAL seeded authors hold zero
 * prompts — a later demo purge (or any user deletion) would CASCADE-DELETE
 * the entire version history of every adopted listing. Version history is
 * the product's git log; it must survive author removal.
 *
 * Why a full table rebuild instead of change(): Laravel's native change()
 * silently no-ops for foreignId columns on SQLite (verified in test) — the
 * only portable way to alter the column AND its FK is the documented
 * create-new + copy + drop + rename pattern. The new table matches the
 * exact shape of migrations 2026_09_11_000400 + 2026_09_29_140200:
 *
 *   id, prompt_id FK→prompts cascade, version_number, body (text NULL),
 *   changelog, variables json NULL, tools json NULL, tags json NULL,
 *   recommended_tools json NULL, audience varchar NULL, tips json NULL
 *   (the 2026_09_16_000100 tooling columns), user_id (NOW nullable, FK
 *   nullOnDelete), status default 'published', timestamps,
 *   UNIQUE(prompt_id, version_number), (prompt_id, created_at), status index.
 *
 * No data is changed: every row keeps its user_id. Replay-safe: a PRAGMA /
 * information_schema nullability check skips an already-applied rebuild
 * (interrupted-run protection, §6.22).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->alreadyApplied()) {
            return;
        }

        Schema::dropIfExists('prompt_versions_new');

        Schema::create('prompt_versions_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('body')->nullable();
            $table->text('changelog')->nullable();
            $table->json('variables')->nullable();
            $table->json('tools')->nullable();
            $table->json('tags')->nullable();
            // Tooling metadata columns (2026_09_16_000100).
            $table->json('recommended_tools')->nullable();
            $table->string('audience', 120)->nullable();
            $table->json('tips')->nullable();
            // THE CHANGE: nullable + nullOnDelete — a deleted author nulls
            // the column; the history row survives ("Former creator" chip).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('published');
            $table->timestamps();

            $table->unique(['prompt_id', 'version_number']);
            $table->index(['prompt_id', 'created_at']);
            $table->index('status');
        });

        DB::statement(
            'insert into prompt_versions_new
                (id, prompt_id, version_number, body, changelog, variables, tools, tags, recommended_tools, audience, tips, user_id, status, created_at, updated_at)
             select id, prompt_id, version_number, body, changelog, variables, tools, tags, recommended_tools, audience, tips, user_id, status, created_at, updated_at
             from prompt_versions'
        );

        Schema::drop('prompt_versions');
        Schema::rename('prompt_versions_new', 'prompt_versions');
    }

    public function down(): void
    {
        // Restore the pre-H2 shape: user_id NOT NULL with cascade FK.
        // Orphaned authorship is backfilled from the owning prompt's creator.
        DB::statement(
            'update prompt_versions set user_id = coalesce((select user_id from prompts where prompts.id = prompt_versions.prompt_id), 0) where user_id is null'
        );

        Schema::dropIfExists('prompt_versions_old');

        Schema::create('prompt_versions_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('body')->nullable();
            $table->text('changelog')->nullable();
            $table->json('variables')->nullable();
            $table->json('tools')->nullable();
            $table->json('tags')->nullable();
            $table->json('recommended_tools')->nullable();
            $table->string('audience', 120)->nullable();
            $table->json('tips')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('published');
            $table->timestamps();

            $table->unique(['prompt_id', 'version_number']);
            $table->index(['prompt_id', 'created_at']);
            $table->index('status');
        });

        DB::statement(
            'insert into prompt_versions_old
                (id, prompt_id, version_number, body, changelog, variables, tools, tags, recommended_tools, audience, tips, user_id, status, created_at, updated_at)
             select id, prompt_id, version_number, body, changelog, variables, tools, tags, recommended_tools, audience, tips, user_id, status, created_at, updated_at
             from prompt_versions'
        );

        Schema::drop('prompt_versions');
        Schema::rename('prompt_versions_old', 'prompt_versions');
    }

    /** True when user_id is already nullable (replay after interruption). */
    private function alreadyApplied(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select('pragma table_info(prompt_versions)') as $column) {
                if ($column->name === 'user_id') {
                    return ((int) $column->notnull) === 0;
                }
            }

            return false;
        }

        $rows = DB::select(
            "select IS_NULLABLE from information_schema.COLUMNS
             where TABLE_SCHEMA = database() and TABLE_NAME = 'prompt_versions' and COLUMN_NAME = 'user_id'"
        );

        return $rows !== [] && reset($rows)->IS_NULLABLE === 'YES';
    }
};
