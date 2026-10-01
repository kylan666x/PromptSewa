<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * M1 (v1.6.0) — a creator withdrawal request (state machine).
 *
 * requested → approved → settled      (happy path)
 * requested → rejected                (admin says no → release row)
 * requested → cancelled               (creator changes mind → release row)
 *
 * The money side of a payout lives in the LEDGER: requesting inserts a
 * withdrawal_hold (−amount); reject/cancel inserts withdrawal_release
 * (+amount). Approve and settled insert NOTHING financial — the hold row
 * was the debit. Rows are never deleted (AGENTS.md #5).
 */
class Payout extends Model
{
    final public const STATUS_REQUESTED = 'requested';

    final public const STATUS_APPROVED = 'approved';

    final public const STATUS_SETTLED = 'settled';

    final public const STATUS_REJECTED = 'rejected';

    final public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_SETTLED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    final public const METHOD_ESEWA_WALLET = 'esewa_wallet';

    final public const METHOD_BANK = 'bank';

    public const METHODS = [
        self::METHOD_ESEWA_WALLET,
        self::METHOD_BANK,
    ];

    protected $fillable = [
        'user_id',
        'amount_paisa',
        'status',
        'method',
        'destination_encrypted',
        'requested_at',
        'decided_by',
        'decided_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount_paisa' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new RuntimeException('payouts are financial records: DELETE is forbidden (AGENTS.md financial invariant #5).');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** The open withdrawal_hold ledger row for this payout, if any. */
    public function holdTransaction(): ?WalletTransaction
    {
        return WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL_HOLD)
            ->where('idempotency_key', 'withdrawal_hold:'.$this->id)
            ->first();
    }

    /**
     * Allowed transitions (M4): decided states are terminal; requested can
     * move to approved (admin), rejected (admin), or cancelled (creator).
     */
    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
            self::STATUS_APPROVED => [self::STATUS_SETTLED, self::STATUS_REJECTED],
            self::STATUS_SETTLED => [],
            self::STATUS_REJECTED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \DomainException("Illegal payout transition: {$this->status} → {$status}");
        }

        $this->status = $status;
        $this->save();
    }
}
