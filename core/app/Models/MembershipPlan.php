<?php

namespace App\Models;

use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * S1 (v1.8.0) — an admin-managed membership plan.
 *
 * perks is a bounded JSON bag (validated in the admin desk):
 *   unlimited_unlock : bool — active members skip Sikka spends (entitlement)
 *   badge_id         : ?int — badge granted on activation
 *   frame_id         : ?int — frame unlocked on activation
 *   grant_verified   : bool — founder-verified toggle on activation
 */
class MembershipPlan extends Model
{
    /** @use HasFactory<MembershipPlanFactory> */
    use HasFactory;

    final public const PERK_UNLIMITED_UNLOCK = 'unlimited_unlock';

    final public const PERK_BADGE_ID = 'badge_id';

    final public const PERK_FRAME_ID = 'frame_id';

    final public const PERK_GRANT_VERIFIED = 'grant_verified';

    protected $fillable = [
        'name',
        'slug',
        'duration_days',
        'price_paisa',
        'stipend_sikka',
        'perks',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_paisa' => 'integer',
            'stipend_sikka' => 'integer',
            'perks' => 'array',
            'active' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    public function perk(string $key, mixed $default = null): mixed
    {
        return ($this->perks ?? [])[$key] ?? $default;
    }

    public function hasUnlimitedUnlock(): bool
    {
        return (bool) $this->perk(self::PERK_UNLIMITED_UNLOCK, false);
    }
}
