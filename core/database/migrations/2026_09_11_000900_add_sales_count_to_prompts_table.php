<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MKT-001: denormalized sales counter (PRD §3.1 creator analytics).
 * Bumped by EntitlementService inside the granting transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_count')->default(0)->index()->after('download_count');
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn('sales_count');
        });
    }
};
