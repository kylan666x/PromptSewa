<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 (v1.7.8) — live notifications v1.
 *
 * cPanel has no websockets: the navbar bell polls a small JSON endpoint
 * every 60 seconds and the dropdown renders the newest 20 rows. Rows are
 * written ONLY by Notification::emit inside each event's transaction, so
 * a notification can never exist without its event (or vice versa).
 *
 * No updated_at: a notification is immutable except for read_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->nullableMorphs('subject');
            $table->string('message', 500);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The bell's two hot queries: unread count, newest 20.
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
