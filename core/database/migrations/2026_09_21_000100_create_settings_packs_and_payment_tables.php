<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce + administration expansion:
 *
 * - `settings`   — runtime-editable admin settings (site name, payment
 *                  gateway credentials). Secret values are encrypted by
 *                  SettingsService before storage (AGENTS.md #9).
 * - `packs`      — curated bundles of prompts sold at one price.
 * - `pack_prompt`— pack contents (many-to-many, idempotent attach).
 * - `tool_logos` — admin-managed AI tool names + logos shown on cards.
 * - `orders`     — records HOW an order was paid (esewa|manual).
 * - `order_items`— items may represent a single product OR a whole pack,
 *                  so product_id/prompt_id become nullable.
 * - `license_grants` — order_item_id loses its UNIQUE constraint: one
 *                  pack line issues many grants (one per member prompt).
 *                  Replay safety moves to the (user_id, prompt_id) active
 *                  grant check inside EntitlementService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_paisa'); // integer paisa, never floats
            $table->string('currency', 3)->default('NPR');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('pack_prompt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pack_id', 'prompt_id']);
        });

        Schema::create('tool_logos', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('logo_path')->nullable(); // storage/app/public relative
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('idempotency_key'); // esewa|manual
            $table->string('payment_reference', 191)->nullable()->after('payment_method'); // gateway txn id / user-supplied proof
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['prompt_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->unsignedBigInteger('prompt_id')->nullable()->change();
            $table->foreignId('pack_id')->nullable()->constrained()->cascadeOnDelete()->after('order_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('prompt_id')->references('id')->on('prompts')->nullOnDelete();
        });

        // MySQL refuses to drop the unique index on order_item_id while the
        // foreign key on the same column still relies on it (error 1553:
        // "needed in a foreign key constraint"). Drop the FK first, then the
        // unique index, then re-add the FK plus a plain (non-unique) index.
        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
        });

        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropUnique(['order_item_id']);
            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->restrictOnDelete();
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        // Mirror of up(): FK must come off before the index swap on MySQL.
        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
        });

        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropIndex(['order_item_id']);
            $table->unique('order_item_id');
            $table->foreign('order_item_id')
                ->references('id')
                ->on('order_items')
                ->restrictOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['prompt_id']);
            $table->dropColumn('pack_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->change();
            $table->unsignedBigInteger('prompt_id')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('prompt_id')->references('id')->on('prompts')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_reference']);
        });

        Schema::dropIfExists('tool_logos');
        Schema::dropIfExists('pack_prompt');
        Schema::dropIfExists('packs');
        Schema::dropIfExists('settings');
    }
};
