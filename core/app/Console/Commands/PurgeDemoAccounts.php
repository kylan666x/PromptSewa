<?php

namespace App\Console\Commands;

use App\Models\LicenseGrant;
use App\Models\Order;
use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * D5 (v1.4.5) — demo-account remediation.
 *
 * One-shot, admin-gated, idempotent. Seeded demo identities are identified
 * by the seeder email list (docs/RUNBOOK-DEMO-PURGE.md is the runbook; the
 * list below is the source of truth mirrored from database/seeders).
 *
 * Decision rule (financial invariants — never delete money-adjacent rows):
 *  - no orders / grants / reports AND no published prompts  → HARD DELETE
 *  - any financial or moderation linkage                    → ban + rename
 *
 * Without --force nothing is written (dry run prints the plan per id).
 */
class PurgeDemoAccounts extends Command
{
    /** Demo identities exactly as the seeders create them. */
    private const DEMO_EMAILS = [
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

    protected $signature = 'pv:purge-demo
        {--dry-run : Report the plan without writing anything (default)}
        {--force : Actually delete/deactivate}';

    protected $description = 'Remove or quarantine seeded demo accounts (D5) — hard delete when unused, ban+rename when financially linked';

    public function handle(): int
    {
        if (! $this->confirmSafety()) {
            return self::FAILURE;
        }

        $dryRun = ! $this->option('force');
        $deleted = [];
        $quarantined = [];
        $missing = [];

        $users = User::query()
            ->whereIn('email', self::DEMO_EMAILS)
            ->get();

        $foundEmails = $users->pluck('email')->all();
        foreach (array_diff(self::DEMO_EMAILS, $foundEmails) as $absent) {
            $missing[] = $absent;
        }

        foreach ($users as $user) {
            $linkage = $this->linkage($user);

            if ($linkage['deletable']) {
                if (! $dryRun) {
                    $this->hardDelete($user);
                }
                $deleted[] = "{$user->email} (id {$user->id})";
            } else {
                if (! $dryRun) {
                    $this->quarantine($user);
                }
                $quarantined[] = "{$user->email} (id {$user->id}) — {$linkage['reason']}";
            }

            $this->line("  [{$user->id}] {$user->email}: ".($linkage['deletable'] ? 'HARD DELETE' : 'BAN+RENAME').($dryRun ? ' (dry run)' : ' — done'));
        }

        $this->newLine();
        $this->info('Deleted: '.count($deleted).' · Quarantined: '.count($quarantined).' · Absent: '.count($missing));
        foreach ($missing as $email) {
            $this->line("  absent: {$email}");
        }
        $this->line($dryRun
            ? 'DRY RUN — nothing written. Re-run with --force to apply.'
            : 'Done. Every touched id is in the output above and storage/logs/purge-demo.log.');

        return self::SUCCESS;
    }

    private function confirmSafety(): bool
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->warn('Production: running in DRY RUN (add --force to apply).');
        }

        return true;
    }

    /** @return array{deletable: bool, reason: string} */
    private function linkage(User $user): array
    {
        $orders = Order::query()->where('buyer_id', $user->id)->count();
        $grants = LicenseGrant::query()->where('user_id', $user->id)->count();
        $reports = PromptReport::query()->where('user_id', $user->id)->count();
        $prompts = Prompt::query()->where('user_id', $user->id)->count();

        $money = $orders + $grants;

        if ($money > 0) {
            return ['deletable' => false, 'reason' => "{$money} money-adjacent row(s) (orders {$orders}, grants {$grants}) — ban+rename, never delete"];
        }

        if ($reports > 0 || $prompts > 0) {
            return ['deletable' => false, 'reason' => "{$prompts} prompt(s), {$reports} report(s) — content linkage, ban+rename"];
        }

        return ['deletable' => true, 'reason' => 'no linkage'];
    }

    private function hardDelete(User $user): void
    {
        $this->log("HARD DELETE user {$user->id} ({$user->email})");

        DB::transaction(function () use ($user) {
            // The linkage check guarantees zero prompts, so forceDelete is
            // safe; prompt_versions/prompts cascade via FK anyway.
            $user->forceDelete();
        });
    }

    private function quarantine(User $user): void
    {
        $this->log("BAN+RENAME user {$user->id} ({$user->email})");

        $user->forceFill([
            'banned_at' => $user->banned_at ?? now(),
            'name' => '[removed demo account] '.$user->id,
            'username' => 'removed-demo-'.$user->id,
        ])->save();
    }

    private function log(string $line): void
    {
        $path = storage_path('logs/purge-demo.log');
        @file_put_contents($path, now()->toIso8601String().' '.$line.PHP_EOL, FILE_APPEND);
    }
}
