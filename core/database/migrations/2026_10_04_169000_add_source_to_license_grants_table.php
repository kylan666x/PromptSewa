<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S2 (v1.8.0) — grant provenance on license_grants.
 *
 * The S2 contract requires the unlimited-unlock bypass to grant with
 * source='membership_unlimited'. issue_reason is free text for staff, so
 * provenance gets its own nullable column instead of overloading it:
 * null = the ordinary purchase path, a named value = the entitlement rail
 * that issued it.
 *
 * Append-only column (no ->after(): the v1.7.8 lesson — never anchor to a
 * column that is still in the parity pending list).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_grants', function (Blueprint $table) {
            $table->string('source', 32)->nullable();
        });
    }

    public function down(): void
    {
        // Guarded drop (MySQL DDL autocommits — replay safety, R2 rule).
        $inspector = app(SchemaInspector::class);

        if ($inspector->hasColumn('license_grants', 'source')) {
            Schema::table('license_grants', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
