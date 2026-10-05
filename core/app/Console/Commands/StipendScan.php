<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

/**
 * S3 (v1.8.0) — the membership stipend + expiry scan.
 *
 * cPanel has no daemons: this command is scheduled daily (and is safe to
 * run any time — by hand after an incident, twice, or late). Idempotency
 * is structural: every stipend row is keyed
 * `stipend:{membership_id}:{period_index}` (UNIQUE), so a replayed run
 * grants each elapsed period exactly once. Expiry is a state flip on the
 * membership row only — ledgers are never rewritten.
 */
class StipendScan extends Command
{
    protected $signature = 'pv:stipend-scan';

    protected $description = 'Grant elapsed membership stipends and expire lapsed memberships (idempotent)';

    public function handle(MembershipService $memberships): int
    {
        $stats = $memberships->scanStipends();

        $this->info("Scanned {$stats['memberships']} membership(s) - granted {$stats['stipends']} stipend(s), expired {$stats['expired']}.");

        return self::SUCCESS;
    }
}
