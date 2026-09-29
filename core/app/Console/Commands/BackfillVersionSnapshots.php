<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Models\PromptVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * T7 (v1.5.0) — version truth backfill.
 *
 * Rows written before v1.5.0 carried no body/variables/tools snapshot (the
 * columns are new). For each prompt, the LATEST version row gets its
 * SCOPE HONESTY: the backfill writes variables + tools (derived from the
 * row's own metadata) but NEVER a body — the body cannot be reconstructed
 * and a fabricated one would lie. Null-body rows keep the honesty chip.
 *
 * Idempotent: only snapshot-less latest rows are written; re-runs no-op.
 */
class BackfillVersionSnapshots extends Command
{
    protected $signature = 'pv:backfill-version-snapshots
        {--dry-run : Report what would be written without writing (default)}
        {--force : Apply the backfill}';

    protected $description = 'Backfill variables/tools snapshots onto the latest prompt_versions rows (T7; bodies are never fabricated)';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        $prompts = Prompt::query()
            ->with(['latestVersion', 'versions'])
            ->get();

        $written = 0;
        $skipped = 0;

        foreach ($prompts as $prompt) {
            $latest = $prompt->latestVersion;

            if ($latest === null || ($latest->variables !== null && $latest->tools !== null)) {
                $skipped++;

                continue;
            }

            $variables = $latest->variableNames();
            $tools = $latest->toolList();

            $this->line("  [prompt {$prompt->id}] {$prompt->slug} — {$latest->label()}: "
                .count($variables).' variable(s), '.count($tools).' tool(s)'
                .($dryRun ? ' (dry run)' : ' — written'));

            if (! $dryRun) {
                DB::transaction(function () use ($latest, $variables, $tools): void {
                    $latest->forceFill([
                        'variables' => $variables,
                        'tools' => $tools,
                    ])->save();
                });
                $written++;
            } else {
                $written++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'DRY RUN — ' : '')."Latest rows backfillable: {$written} · already snapshotted / no versions: {$skipped}");

        if ($dryRun) {
            $this->line('Re-run with --force to apply.');
        }

        return self::SUCCESS;
    }
}
