<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-UI-001 content model upgrade (God of Prompt research):
 *
 * - `prompts.type`: text|image|video — drives dynamic form placeholders in
 *   the create/edit flow and image-first layouts on detail pages.
 * - `categories.type_scope`: null = universal category; text|image|video =
 *   only offered for that prompt type in forms (God of Prompt keeps image
 *   generation in its own space).
 * - `prompt_versions.recommended_tools`: which AI tools this version runs
 *   best in (["Midjourney", "DALL-E"]) — surfaced as chips on detail pages.
 * - `prompt_versions.audience`: "Perfect for" line (e.g. "Photographers").
 * - `prompt_versions.tips`: ordered usage tips shown below the prompt body.
 *
 * All new columns are nullable/defaulted — non-destructive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->string('type', 20)->default('text')->index()->after('status');
        });

        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->json('recommended_tools')->nullable()->after('tags');
            $table->string('audience', 120)->nullable()->after('recommended_tools');
            $table->json('tips')->nullable()->after('audience');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('type_scope', 20)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('prompt_versions', function (Blueprint $table) {
            $table->dropColumn(['recommended_tools', 'audience', 'tips']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type_scope');
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
