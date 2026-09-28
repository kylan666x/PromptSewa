<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * S4 backfill: every legacy account gets a handle derived from its display
 * name (Str::slug + numeric suffix on collision). Idempotent — rows that
 * already have a username are untouched, so re-running after a partial
 * failure completes rather than duplicating.
 */
return new class extends Migration
{
    public function up(): void
    {
        $taken = User::query()->whereNotNull('username')->pluck('username')
            ->map(fn (string $u) => mb_strtolower($u))
            ->flip();

        User::query()->whereNull('username')->orderBy('id')->chunkById(100, function ($users) use (&$taken) {
            foreach ($users as $user) {
                $base = Str::slug($user->name) ?: 'user';
                $base = mb_substr($base, 0, 24); // leave room for a suffix
                $handle = $base;
                $suffix = 1;

                while ($taken->has($handle)) {
                    $handle = $base.($suffix++);
                }

                $user->forceFill(['username' => $handle])->save();
                $taken->put($handle, true);
            }
        });
    }

    public function down(): void
    {
        // Backfill is not reversible without data loss — leave handles in
        // place; the NOT NULL migration's down() relaxes the column instead.
    }
};
