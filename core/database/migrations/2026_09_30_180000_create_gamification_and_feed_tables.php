<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G2/G3/G4 (v1.7.0) — Community & Gamification schema.
 *
 * badges + user_badges: the achievements engine. Award idempotency is the
 * UNIQUE(user, badge) constraint — a double-fire observer insert throws
 * and is caught+ignored at the single award choke point.
 *
 * frames + users.active_frame_id: cosmetic avatar rings; nullOnDelete so
 * removing a frame never breaks a user row (it just clears the ring).
 *
 * feed_events: the news pulse. Written ONLY by the G2 observers inside the
 * triggering transaction (never from controllers). Idempotency: a per-type
 * sensible unique key — milestone events dedupe on (type, subject, day)
 * via the meta payload check in the emitter rather than a hard index,
 * because prompt_published is legitimately repeatable across versions.
 *
 * users.xp: integer counter (pure-PHP threshold fn renders "Lv N").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->string('image_path')->nullable(); // badges/…, alpha PNG
            // first_publish | first_sale | sales_10 | sales_50 | verified | top_rated
            $table->string('criterion', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['criterion', 'is_active']);
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            // THE idempotency gate: a user holds a badge at most once.
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('frames', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('image_path'); // frames/…, alpha PNG
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // xp already exists since v1.1 (2026_09_11_000100) — only the
            // frame FK is new here.
            $table->foreignId('active_frame_id')->nullable()->constrained('frames')->nullOnDelete();
        });

        Schema::create('feed_events', function (Blueprint $table) {
            $table->id();
            // prompt_published | sale_milestone | badge_earned | pack_created
            $table->string('type', 40)->index();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            // Idempotency marker written by the emitter (nullable — versioned
            // publishes repeat legitimately, so the uniqueness is enforced
            // per-type in the emitter, not by a global index).
            $table->string('dedupe_key')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_events');

        Schema::table('users', function (Blueprint $table) {
            // xp predates v1.7.0 — leave it; only the frame FK goes.
            $table->dropConstrainedForeignIndex(['active_frame_id']);
            $table->dropColumn('active_frame_id');
        });

        Schema::dropIfExists('frames');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
