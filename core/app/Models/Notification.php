<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * F6 (v1.7.8) — one bell row for one user-visible event.
 *
 * Written ONLY through emit(), and ONLY from inside the emitting event's
 * own transaction (order approval, verified flip, badge award, frame
 * unlock, payout decision, report triage, comp grant) — a notification
 * can never exist without its event, or vice versa (same discipline as
 * the FeedEvent observers).
 *
 * Immutable except read_at. targetUrl() is the click-through destination.
 */
class Notification extends Model
{
    public const TYPE_ORDER_APPROVED = 'order_approved';

    public const TYPE_ORDER_REJECTED = 'order_rejected';

    public const TYPE_VERIFIED_GRANTED = 'verified_granted';

    public const TYPE_VERIFIED_REVOKED = 'verified_revoked';

    public const TYPE_BADGE_AWARDED = 'badge_awarded';

    public const TYPE_FRAME_UNLOCKED = 'frame_unlocked';

    public const TYPE_PAYOUT_SETTLED = 'payout_settled';

    public const TYPE_PAYOUT_REJECTED = 'payout_rejected';

    public const TYPE_REPORT_RESOLVED = 'report_resolved';

    public const TYPE_REPORT_DISMISSED = 'report_dismissed';

    public const TYPE_COMP_GRANT = 'comp_grant';

    // S4/S6 (v1.8.0): the Sikka rail's own bell rows — top-up credited,
    // membership stipend granted. Cash-out decisions reuse the payout types.
    public const TYPE_SIKKA_TOPUP = 'sikka_topup';

    public const TYPE_SIKKA_STIPEND = 'sikka_stipend';

    public const TYPES = [
        self::TYPE_ORDER_APPROVED,
        self::TYPE_ORDER_REJECTED,
        self::TYPE_VERIFIED_GRANTED,
        self::TYPE_VERIFIED_REVOKED,
        self::TYPE_BADGE_AWARDED,
        self::TYPE_FRAME_UNLOCKED,
        self::TYPE_PAYOUT_SETTLED,
        self::TYPE_PAYOUT_REJECTED,
        self::TYPE_REPORT_RESOLVED,
        self::TYPE_REPORT_DISMISSED,
        self::TYPE_COMP_GRANT,
        self::TYPE_SIKKA_TOPUP,
        self::TYPE_SIKKA_STIPEND,
    ];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'subject_type',
        'subject_id',
        'message',
        'read_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * The single emitter. Call ONLY inside the emitting event's
     * transaction — the row commits or rolls back with the event.
     */
    public static function emit(User $user, string $type, string $message, ?Model $subject = null): self
    {
        return static::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'message' => mb_substr($message, 0, 500),
            'created_at' => now(),
        ]);
    }

    /** Where a click-through lands (subject page when it still exists). */
    public function targetUrl(): string
    {
        return match ($this->type) {
            self::TYPE_ORDER_APPROVED, self::TYPE_ORDER_REJECTED => $this->subject instanceof Order
                ? route('checkout.show', $this->subject)
                : route('purchases.index'),
            self::TYPE_COMP_GRANT => route('purchases.index'),
            self::TYPE_PAYOUT_SETTLED, self::TYPE_PAYOUT_REJECTED => route('dashboard.earnings'),
            self::TYPE_SIKKA_TOPUP, self::TYPE_SIKKA_STIPEND => route('dashboard.earnings'),
            self::TYPE_BADGE_AWARDED, self::TYPE_FRAME_UNLOCKED,
            self::TYPE_VERIFIED_GRANTED, self::TYPE_VERIFIED_REVOKED => route('dashboard.profile.edit'),
            default => route('dashboard'),
        };
    }
}
