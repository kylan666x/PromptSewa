<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S1 (v1.9.0) — retire the Sikka kill-switch.
 *
 * v1.8.0 shipped sikka_enabled OFF until the founder flipped it. Sikka is
 * now the permanent economy: this migration flips the shipped row ON for
 * every install (fresh and existing) and the desk no longer exposes the
 * toggle. The key itself survives for backward compatibility — old gates
 * still read it, and they must find "1".
 *
 * insertOrIgnore first, then update: an install where the v1.8.0 row was
 * deleted converges to the same state instead of depending on that
 * migration having run.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore(['key' => 'sikka_enabled', 'value' => '1']);
        DB::table('settings')->where('key', 'sikka_enabled')->update(['value' => '1']);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'sikka_enabled')->update(['value' => '0']);
    }
};
