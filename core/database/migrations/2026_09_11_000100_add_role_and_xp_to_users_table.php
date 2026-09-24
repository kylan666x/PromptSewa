<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PromptSewa roles & gamification.
 *
 * Kept separate from the skeleton users migration so a cPanel deployment
 * can extend the exact same table Laravel's auth scaffolding created.
 *
 * cPanel note: MariaDB has no native enum-index magic — we use a string
 * column with an index because RBAC values change rarely but are queried
 * on every leaderboard/moderation query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // RBAC: guest is implicit (no row), member is the default.
            $table->string('role', 20)->default('member')->index()->after('password');
            // Gamification (PRD §4): XP accrues from downloads, reviews, sales...
            $table->unsignedBigInteger('xp')->default(0)->index()->after('role');
            // Profile customization (PRD §4): banners live in local/S3 storage.
            $table->string('banner_path')->nullable()->after('xp');
            $table->string('avatar_path')->nullable()->after('banner_path');
            $table->text('bio')->nullable()->after('avatar_path');
            // Creator payout handle (eSewa / manual banking), set on upgrade.
            $table->string('payout_handle')->nullable()->after('bio');
            $table->timestamp('promoted_to_creator_at')->nullable()->after('payout_handle');
            $table->softDeletes()->after('promoted_to_creator_at'); // moderation needs reversible bans
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'xp', 'banner_path', 'avatar_path', 'bio',
                'payout_handle', 'promoted_to_creator_at', 'deleted_at',
            ]);
        });
    }
};
