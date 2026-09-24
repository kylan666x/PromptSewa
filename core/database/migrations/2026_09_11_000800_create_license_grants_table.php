<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-001: license_grants (entitlements) — proof a user may use a prompt
 * under a license tier. Created ONLY by EntitlementService after payment
 * confirmation, never before (AGENTS.md financial invariant #4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete(); // entitlements survive user deletion for audit
            $table->foreignId('order_item_id')
                ->unique() // one grant per purchased line — idempotency at the schema level
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('prompt_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('license_tier', 20); // personal|commercial (mirrors prompts.license_tier at purchase)
            // Public-safe token shown in the user's library (never the PK).
            $table->string('grant_code', 40)->unique();
            $table->string('status', 20)->default('active')->index(); // active|revoked
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // "My library" query: all active grants for a user.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_grants');
    }
};
