<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Scheduled nightly (see routes/console.php). Results are cached so the
 * public leaderboard pages never hit the DB directly on shared hosting.
 */
class RecomputeLeaderboards extends Command
{
    protected $signature = 'promptsewa:recompute-leaderboards';

    protected $description = 'Recalculate XP leaderboards and cache the results for public pages';

    public function handle(): int
    {
        $topCreators = User::query()
            ->orderByDesc('xp')
            ->limit(50)
            ->get(['id', 'name', 'xp', 'avatar_path']);

        cache()->put('leaderboards.top', $topCreators, now()->addDay());

        $this->info('Leaderboards recomputed and cached ('.$topCreators->count().' users).');

        return self::SUCCESS;
    }
}
