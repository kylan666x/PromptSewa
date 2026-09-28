<?php

namespace App\Services;

use App\Models\LicenseGrant;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A5: complimentary ("comp") grants.
 *
 * Admins issue free licenses outside the order pipeline — press copies,
 * partner giveaways, support make-goods. Every comp is a real LicenseGrant
 * (same entitlement checks as a purchase) but is marked tier `comp` and
 * carries the issuing admin's id plus a reason in the ledger row, so a
 * later audit can answer "why does this account own this prompt?".
 *
 * Idempotent per (user, prompt, active): re-running the same comp never
 * double-issues while the earlier grant is still active.
 */
class CompGrantService
{
    /**
     * Grant one user a comp license for one prompt.
     *
     * @return LicenseGrant|null the created grant, or null when the user
     *                          already holds an active license for it
     */
    public function grant(User $user, Prompt $prompt, User $issuer, string $reason): ?LicenseGrant
    {
        return DB::transaction(function () use ($user, $prompt, $issuer, $reason) {
            $exists = LicenseGrant::query()
                ->where('user_id', $user->id)
                ->where('prompt_id', $prompt->id)
                ->where('status', LicenseGrant::STATUS_ACTIVE)
                ->exists();

            if ($exists) {
                return null;
            }

            return LicenseGrant::create([
                'user_id' => $user->id,
                'order_item_id' => null, // comps are outside the order pipeline
                'prompt_id' => $prompt->id,
                'license_tier' => 'comp',
                'grant_code' => strtoupper(Str::random(12)).'-'.Str::random(8),
                'status' => LicenseGrant::STATUS_ACTIVE,
                'issued_by' => $issuer->id,
                'issue_reason' => substr(trim($reason), 0, 500),
            ]);
        });
    }

    /** Revoke a comp (sets status revoked — ledger of record stays intact). */
    public function revoke(LicenseGrant $grant, User $issuer): void
    {
        $grant->fill(['status' => LicenseGrant::STATUS_REVOKED])->save();
    }
}
