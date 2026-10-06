<?php

namespace App\Models;

use App\Services\SettingsService;
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
        'price_sikka',
        'price_paisa',
        'stipend_sikka',
        'perks',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_sikka' => 'integer',
            'price_paisa' => 'integer',
            'stipend_sikka' => 'integer',
            'perks' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * S1b (v1.9.0): Sikka is the membership price of record — the same
     * mirror contract prompts and packs carry (price_paisa stays the
     * derived NPR mirror for order snapshots and the settlement desk).
     */
    protected static function booted(): void
    {
        static::saving(function (MembershipPlan $plan): void {
            $sikkaDirty = $plan->isDirty('price_sikka');
            $paisaDirty = $plan->isDirty('price_paisa');

            if (! $sikkaDirty && ! $paisaDirty) {
                return;
            }

            $buy = $plan->buyRatePaisa();

            if ($sikkaDirty && ! $paisaDirty) {
                $plan->price_paisa = max(0, (int) $plan->price_sikka) * $buy;
            } elseif ($paisaDirty && ! $sikkaDirty) {
                $paisa = max(0, (int) $plan->price_paisa);
                $plan->price_sikka = $paisa === 0 ? 0 : intdiv($paisa + $buy - 1, $buy);
            }
        });
    }

    /** The configured buy rate in paisa per Sikka (default 100 = NPR 1). */
    public function buyRatePaisa(): int
    {
        $value = (int) app(SettingsService::class)->get('sikka_buy_paisa_per_token', '100');

        return $value > 0 ? $value : 100;
    }

    /** S1b (v1.9.0): the membership price of record — credits, 0 = free. */
    public function priceSikka(): int
    {
        return max(0, (int) $this->price_sikka);
    }

    public function isFree(): bool
    {
        return $this->priceSikka() === 0;
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
