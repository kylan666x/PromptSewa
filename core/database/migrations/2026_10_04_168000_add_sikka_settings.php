<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S1 (v1.8.0) — Sikka settings defaults.
 *
 * sikka_enabled is the kill-switch: OFF until the founder flips it. While
 * off, every Sikka surface renders nothing and the NPR rails behave exactly
 * as they did in v1.7.8 (locked by the dual-rail kill-switch test).
 *
 * Bounds live in the admin desk validation, not in the column — settings
 * are strings, and the defaults here are the documented values.
 * insertOrIgnore: a replay on an install that already has the rows is a
 * clean no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            ['key' => 'sikka_enabled', 'value' => '0'],
            // Buy rate: paisa paid per Sikka (default 1 Sikka = NPR 1).
            ['key' => 'sikka_buy_paisa_per_token', 'value' => '100'],
            // Cash-out rate: paisa paid per Sikka when a creator withdraws.
            ['key' => 'sikka_cashout_paisa_per_token', 'value' => '80'],
            // Engagement rewards (spend-only Sikka) + the daily cap.
            ['key' => 'engage_daily_sikka', 'value' => '1'],
            ['key' => 'engage_publish_sikka', 'value' => '2'],
            ['key' => 'engage_rating_sikka', 'value' => '1'],
            ['key' => 'engage_daily_cap_sikka', 'value' => '5'],
            // Minimum Sikka per withdrawal request.
            ['key' => 'sikka_cashout_min', 'value' => '500'],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'sikka_enabled',
            'sikka_buy_paisa_per_token',
            'sikka_cashout_paisa_per_token',
            'engage_daily_sikka',
            'engage_publish_sikka',
            'engage_rating_sikka',
            'engage_daily_cap_sikka',
            'sikka_cashout_min',
        ])->delete();
    }
};
