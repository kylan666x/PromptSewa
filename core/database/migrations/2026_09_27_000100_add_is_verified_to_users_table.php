<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verified badge (Facebook/X-style) in the PromptSewa brand color.
 *
 * `is_verified` is issued manually by admins from the admin Users page —
 * no automatic criteria. Renders site-wide wherever a user name appears
 * (navbar, prompt cards, creator profiles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_verified');
        });
    }
};
