<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.7.5 (R3) — per-frame centre-hole tolerance.
 *
 * A frame is a 512×512 PNG whose transparent centre hole the photo must
 * fill exactly. Art differs: the shipped ring is 62%, while the founder's
 * "Abyssal" ring measures 37.5% (largest fully transparent disc = 192px of
 * 512, centred at 258,254). Before this column every avatar hard-coded one
 * geometry, so a ring with a different hole could not line up — the
 * v1.7.5 frame-misalignment incident.
 *
 * The column is the DATA half of the fix; the bound lives in ONE place
 * (App\Models\Frame::HOLE_MIN / HOLE_MAX) and is enforced by the model, the
 * admin form and the tests, because a portable CHECK constraint cannot be
 * ADDED to the existing `frames` table on SQLite (ALTER TABLE ... ADD
 * CONSTRAINT is not supported there) and a hand-rolled per-driver CHECK
 * would drift. tinyint unsigned already rejects negatives on MySQL.
 *
 * Backfill: adding a NOT NULL column with a DEFAULT writes 62 to every
 * existing row, so all shipped art keeps its current geometry on upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frames', function (Blueprint $table) {
            $table->tinyInteger('hole_percent')->unsigned()->default(62);
        });
    }

    public function down(): void
    {
        Schema::table('frames', function (Blueprint $table) {
            $table->dropColumn('hole_percent');
        });
    }
};
