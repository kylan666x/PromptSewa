<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W4/W3 (v1.7.3) — Frame Truth.
 *
 * frames.criterion: nullable criterion enum shared with badges
 * (first_publish | first_sale | sales_10 | sales_50 | verified | top_rated)
 * plus the literal 'manual'. NULL = a free frame anyone can equip.
 *
 * frames.animation: none | spin | pulse | shine — a CSS-only motion class
 * applied to the ring overlay (decorative-only, keyframes gated behind
 * prefers-reduced-motion: no-preference in app.css).
 *
 * user_frame_unlocks: the award ledger for earned/manual frames. A user
 * holds a frame at most once (UNIQUE(user, frame) IS the idempotency gate)
 * with source = manual and an audited granted_by + reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frames', function (Blueprint $table) {
            // Shared criterion vocabulary with badges + the manual literal.
            if (! Schema::hasColumn('frames', 'criterion')) {
                $table->string('criterion', 40)->nullable()->after('image_path')->index();
            }

            if (! Schema::hasColumn('frames', 'animation')) {
                $table->string('animation', 20)->default('none')->after('criterion');
            }
        });

        Schema::create('user_frame_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('frame_id')->constrained()->cascadeOnDelete();
            // manual (admin award) — auto criteria grant unlocks implicitly.
            $table->string('source', 20)->default('manual');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            // THE idempotency gate: a user holds a frame at most once.
            $table->unique(['user_id', 'frame_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_frame_unlocks');

        Schema::table('frames', function (Blueprint $table) {
            if (Schema::hasColumn('frames', 'criterion')) {
                $table->dropIndex(['criterion']);
                $table->dropColumn('criterion');
            }

            if (Schema::hasColumn('frames', 'animation')) {
                $table->dropColumn('animation');
            }
        });
    }
};
