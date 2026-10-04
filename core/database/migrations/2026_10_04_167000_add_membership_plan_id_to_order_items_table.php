<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S5 (v1.8.0) — membership purchase rail (mediator ruling).
 *
 * Membership plans are sold through the ordinary order pipeline: a plan
 * line is an order_item carrying membership_plan_id, priced in NPR
 * (price_paisa) and paid through the manual/eSewa rails. On approval the
 * admin transition calls the membership activation choke point.
 *
 * Nullable FK, restrictOnDelete — plan rows must survive for audit once
 * a purchase references them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Guarded drops (MySQL DDL autocommits — replay safety, R2 rule).
        $inspector = app(SchemaInspector::class);

        if ($inspector->hasForeignKey('order_items', 'membership_plan_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['membership_plan_id']);
            });
        }

        if ($inspector->hasColumn('order_items', 'membership_plan_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('membership_plan_id');
            });
        }
    }
};
