<?php

namespace App\Policies;

use App\Models\LicenseGrant;
use App\Models\User;

/**
 * MKT-001 requirement 7: users may only view their own entitlements.
 */
class LicenseGrantPolicy
{
    public function view(User $user, LicenseGrant $grant): bool
    {
        return $grant->user_id === $user->id || $user->isModerator();
    }
}
