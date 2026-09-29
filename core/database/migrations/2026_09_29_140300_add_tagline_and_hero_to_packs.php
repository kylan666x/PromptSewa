<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T8 (v1.5.0): pack landing pages.
 *
 * tagline/hero_copy power the public paper-world landing page. Slugs
 * already exist (unique since the packs table was created) — nothing to
 * add there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            if (! Schema::hasColumn('packs', 'tagline')) {
                $table->string('tagline', 200)->nullable()->after('description');
            }

            if (! Schema::hasColumn('packs', 'hero_copy')) {
                $table->text('hero_copy')->nullable()->after('tagline');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packs', function (Blueprint $table) {
            foreach (['hero_copy', 'tagline'] as $column) {
                if (Schema::hasColumn('packs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
