<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI-001: public storefront support fields.
 *
 * - `cover_image_path`: optional card/detail artwork; NULL = the UI renders a
 *   deterministic visual placeholder instead (no broken images, ever).
 * - `visibility`: public|private — private listings are creator-only and are
 *   excluded from every public surface, independent of review status.
 *
 * Both columns are nullable/defaulted so this migration is non-destructive
 * and safe to run against a populated cPanel database (no table rebuild).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
            $table->string('visibility', 20)->default('public')->index()->after('status'); // public|private

            // Composite index for the public listing feed queries
            // (status + visibility are always constrained together).
            $table->index(['visibility', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'status', 'created_at']);
            $table->dropColumn(['cover_image_path', 'visibility']);
        });
    }
};
