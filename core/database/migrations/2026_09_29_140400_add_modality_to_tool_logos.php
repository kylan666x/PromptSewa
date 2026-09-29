<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T6 (v1.5.0): tool modality.
 *
 * modality scopes a tool to a prompt type (text|image|video|agentic|skill)
 * or `any` (works everywhere). Existing rows backfill to `any` here; the
 * TaxonomySeeder then sets the seeded catalog's true modalities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tool_logos', function (Blueprint $table) {
            if (! Schema::hasColumn('tool_logos', 'modality')) {
                $table->string('modality', 20)->default('any')->after('name')->index();
            }
        });

        // Backfill existing rows to `any` (T6 directive) before the seeder
        // assigns specific modalities to the seeded catalog.
        DB::table('tool_logos')->whereNull('modality')->update(['modality' => 'any']);
    }

    public function down(): void
    {
        Schema::table('tool_logos', function (Blueprint $table) {
            if (Schema::hasColumn('tool_logos', 'modality')) {
                $table->dropColumn('modality');
            }
        });
    }
};
