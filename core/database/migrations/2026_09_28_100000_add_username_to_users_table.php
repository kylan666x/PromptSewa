<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint "Search & Profile UX": every user gets an optional public handle.
 *
 * Nullable by design — legacy accounts (and the seeded demo creators) keep
 * working until they pick one in /dashboard/profile. URLs resolve
 * username-first with a name fallback (see AppServiceProvider::boot and
 * User::getRouteKey()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
