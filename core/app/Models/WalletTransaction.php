<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * M1 (v1.6.0) — one immutable row in the insert-only wallet ledger.
 *
 * The insert-only invariant is enforced at RUNTIME, not just by convention:
 * any ->save() on an existing row or any ->delete() throws immediately.
 * Balances are SUM(amount_paisa) computed by WalletService under
 * lockForUpdate() — this model holds no balance column and never will.
 *
 * Types: sale_credit (+, creator's share of an order line),
 * withdrawal_hold (−, open payout request), withdrawal_release (+,
 * rejected/cancelled payout returning the hold), adjustment (±, admin
 * correction with a mandatory meta.reason).
 */
class WalletTransaction extends Model
{
    final public const TYPE_SALE_CREDIT = 'sale_credit';

    final public const TYPE_WITHDRAWAL_HOLD = 'withdrawal_hold';

    final public const TYPE_WITHDRAWAL_RELEASE = 'withdrawal_release';

    final public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_SALE_CREDIT,
        self::TYPE_WITHDRAWAL_HOLD,
        self::TYPE_WITHDRAWAL_RELEASE,
        self::TYPE_ADJUSTMENT,
    ];

    /**
     * The ledger is insert-only: no updated_at, ever.
     * created_at is managed explicitly by the writers.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount_paisa',
        'order_id',
        'order_item_id',
        'idempotency_key',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paisa' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // M6 release gate: the model itself refuses mutation/deletion.
        static::updating(function () {
            throw new RuntimeException('wallet_transactions is insert-only: UPDATE is forbidden (AGENTS.md financial invariant #2).');
        });

        static::deleting(function () {
            throw new RuntimeException('wallet_transactions is insert-only: DELETE is forbidden (AGENTS.md financial invariant #2).');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
