<?php

namespace App\Models;

use Database\Factories\SikkaTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * S1 (v1.8.0) — one immutable row in the insert-only Sikka ledger.
 *
 * The insert-only invariant is enforced at RUNTIME, not just by convention:
 * any write to an existing row or any delete throws immediately (the
 * wallet guard, mirrored). Balances are
 * SUM(amount_sikka) computed by SikkaService under lockForUpdate() — this
 * model holds no balance column and never will.
 *
 * Sikka is a credit, not a currency: amounts are integer, never decimal.
 * cashout_eligible is decided at write time (S2 contract):
 *   - true:  topup | topup_bonus | sale_credit | spend | membership_stipend
 *            | payout_hold | payout_release
 *   - false: engagement_reward | admin_grant (spend-only by default; an
 *            admin may flip ONE grant with a mandatory reason, audited in
 *            meta + the admin log)
 */
class SikkaTransaction extends Model
{
    /** @use HasFactory<SikkaTransactionFactory> */
    use HasFactory;

    final public const TYPE_TOPUP = 'topup';

    final public const TYPE_TOPUP_BONUS = 'topup_bonus';

    final public const TYPE_SALE_CREDIT = 'sale_credit';

    final public const TYPE_SPEND = 'spend';

    final public const TYPE_MEMBERSHIP_STIPEND = 'membership_stipend';

    final public const TYPE_ENGAGEMENT_REWARD = 'engagement_reward';

    final public const TYPE_ADMIN_GRANT = 'admin_grant';

    final public const TYPE_PAYOUT_HOLD = 'payout_hold';

    final public const TYPE_PAYOUT_RELEASE = 'payout_release';

    public const TYPES = [
        self::TYPE_TOPUP,
        self::TYPE_TOPUP_BONUS,
        self::TYPE_SALE_CREDIT,
        self::TYPE_SPEND,
        self::TYPE_MEMBERSHIP_STIPEND,
        self::TYPE_ENGAGEMENT_REWARD,
        self::TYPE_ADMIN_GRANT,
        self::TYPE_PAYOUT_HOLD,
        self::TYPE_PAYOUT_RELEASE,
    ];

    /**
     * The ledger is insert-only: no updated_at, ever.
     * created_at is managed explicitly by the writers.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'amount_sikka',
        'cashout_eligible',
        'order_id',
        'idempotency_key',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_sikka' => 'integer',
            'cashout_eligible' => 'boolean',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('sikka_transactions is insert-only: UPDATE is forbidden (AGENTS.md financial invariant #2).');
        });

        static::deleting(function () {
            throw new RuntimeException('sikka_transactions is insert-only: DELETE is forbidden (AGENTS.md financial invariant #2).');
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
}
