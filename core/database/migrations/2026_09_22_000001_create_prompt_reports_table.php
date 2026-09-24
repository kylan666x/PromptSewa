<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Report this prompt" — public abuse reports with a light moderation
 * workflow: open → resolved | dismissed (resolved_by/at recorded).
 * Guests may report (rate-limited); logged-in reporters are linked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40);
            $table->text('message');
            $table->string('reporter_email')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_reports');
    }
};
