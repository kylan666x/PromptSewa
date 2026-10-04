<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * S1 (v1.8.0) — a membership instance.
 *
 *   active → expired   (pv:stipend-scan, ends_at passed — state change on
 *                       this row ONLY; ledgers are never rewritten)
 *   active → cancelled (admin action)
 *
 * An unlimited_unlock membership is an ENTITLEMENT, never a balance: the
 * buyer's Sikka ledger is untouched, the checkout bypass simply grants the
 * license with source='membership_unlimited' (S2 contract).
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    final public const STATUS_ACTIVE = 'active';

    final public const STATUS_EXPIRED = 'expired';

    final public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    /** Stipend periods are 30 days; period_index counts from the start. */
    final public const PERIOD_DAYS = 30;

    protected $fillable = [
        'user_id',
        'plan_id',
        'starts_at',
        'ends_at',
        'status',
        'source_order_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    /** Illegal transitions throw — membership history is never rewritten silently. */
    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_ACTIVE => [self::STATUS_EXPIRED, self::STATUS_CANCELLED],
            self::STATUS_EXPIRED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \DomainException("Illegal membership transition: {$this->status} → {$status}");
        }

        $this->status = $status;
        $this->save();
    }
}
