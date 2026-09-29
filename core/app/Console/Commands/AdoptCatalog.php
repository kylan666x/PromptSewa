<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * T4 (v1.5.0) — catalog adoption.
 *
 * Reassigns every prompt owned by a seeded demo/bulk-catalog identity to
 * the official PromptSewa house account (created/flagged by pv:official-account).
 *
 * Scope discipline (what this deliberately does NOT touch):
 *  - packs stay admin-owned (packs are merchandising, not authorship)
 *  - orders and license grants are untouched (money invariants — v1.6.0 ledger)
 *  - prompt_versions.user_id is left as the original author so version
 *    history remains an honest record; only the owning listing moves.
 *
 * Default is a dry run; nothing is written without --force. Idempotent:
 * a second run finds no candidate owners and exits cleanly.
 */
class AdoptCatalog extends Command
{
    /** Seeder identities whose prompts are adopted. Mirrors PurgeDemoAccounts. */
    private const CANDIDATE_EMAILS = [
        'admin@promptsewa.test',
        'bibek@promptsewa.test',
        'maya@promptsewa.test',
        'dorje@promptsewa.test',
        'bibek@promptvellum.test',
        'maya@promptvellum.test',
        'dorje@promptvellum.test',
        'justshipitai@gmail.com',
        'test@example.com',
    ];

    protected $signature = 'pv:adopt-catalog
        {--dry-run : Print the adoption plan without writing (default)}
        {--force : Apply the reassignment}';

    protected $description = 'Reassign seeded demo/bulk-catalog prompts to the official PromptSewa account (T4)';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        $official = User::query()
            ->where('username', 'promptsewa')
            ->orWhere('is_official', true)
            ->first();

        if (! $official) {
            $this->error('Official account not found. Run `php artisan pv:official-account` first.');

            return self::FAILURE;
        }

        $owners = User::query()
            ->whereIn('email', self::CANDIDATE_EMAILS)
            ->whereKeyNot($official->id)
            ->withCount('prompts')
            ->get();

        $candidates = $owners->filter(fn (User $u) => $u->prompts_count > 0);

        if ($candidates->isEmpty()) {
            $this->info('Nothing to adopt — no candidate owner holds any prompt.');

            return self::SUCCESS;
        }

        $this->info("Official account: @{$official->username} (id {$official->id})");
        $this->newLine();

        $total = 0;
        foreach ($candidates as $owner) {
            $types = Prompt::query()
                ->where('user_id', $owner->id)
                ->selectRaw('type, count(*) as n')
                ->groupBy('type')
                ->pluck('n', 'type');

            $this->line("  [{$owner->id}] {$owner->email} — {$owner->prompts_count} prompt(s)"
                .($types->isNotEmpty() ? ' ('.$types->map(fn ($n, $t) => "{$t}: {$n}")->implode(', ').')' : ''));

            $total += $owner->prompts_count;
        }

        $this->newLine();
        $this->info("Total prompts to adopt: {$total} → @{$official->username}");

        if ($dryRun) {
            $this->warn('DRY RUN — nothing written. Architect authorization required before --force.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates, $official, &$total) {
            foreach ($candidates as $owner) {
                $moved = Prompt::query()
                    ->where('user_id', $owner->id)
                    ->update(['user_id' => $official->id]);
                $this->line("  adopted {$moved} prompt(s) from {$owner->email}");
            }
        });

        $this->info("Done. {$total} prompt(s) now owned by @{$official->username}. Packs, orders, and grants untouched.");

        return self::SUCCESS;
    }
}
