<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T1 (v1.5.0): the official house account flag.
 *
 * users.is_official marks the PromptSewa house account (username
 * `promptsewa`, created by `pv:official-account`). It drives the official
 * blue badge (T2) and the admin-only edit guard. Append-only; never a
 * demo seeder product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_official')->default(false)->after('is_verified')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_official');
        });
    }
};
