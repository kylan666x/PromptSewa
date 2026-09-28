<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A4 ban/disable: nullable timestamp — null = active account.
            // A timestamp (not a bool) records WHEN the ban was issued, which
            // the admin users table shows for dispute context.
            $table->timestamp('banned_at')->nullable()->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('banned_at');
        });
    }
};
