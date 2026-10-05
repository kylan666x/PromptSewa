<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S2 (v1.8.0) — Sikka top-up lines ride the ordinary order pipeline.
 *
 * A sikka_packs row is bought through the NPR rails as an order line
 * carrying sikka_pack_id, priced in NPR (price_paisa). On approval
 * SikkaService::topupCredit credits sikka_amount + bonus_sikka as two
 * ledger rows. Nullable FK, restrictOnDelete — pack rows must survive for
 * audit once a purchase references them (mirrors 167000 for plans).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('sikka_pack_id')->nullable()->constrained('sikka_packs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Guarded drops (MySQL DDL autocommits — replay safety, R2 rule).
        $inspector = app(SchemaInspector::class);

        if ($inspector->hasForeignKey('order_items', 'sikka_pack_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['sikka_pack_id']);
            });
        }

        if ($inspector->hasColumn('order_items', 'sikka_pack_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('sikka_pack_id');
            });
        }
    }
};
