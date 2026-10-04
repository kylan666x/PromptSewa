<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    final public const STATUS_PENDING = 'pending';

    final public const STATUS_PAID = 'paid';

    final public const STATUS_FAILED = 'failed';

    final public const STATUS_REFUNDED = 'refunded';

    /** S1 (v1.8.0): the rail marker in `currency` — npr | sikka. */
    final public const CURRENCY_NPR = 'npr';

    final public const CURRENCY_SIKKA = 'sikka';

    protected $fillable = [
        'buyer_id',
        'status',
        'subtotal_paisa',
        'tax_paisa',
        'total_paisa',
        'currency',
        'sikka_amount',
        'meta',
        'idempotency_key',
        'payment_method',
        'payment_reference',
        'manual_txn_id',
        'manual_proof_path',
        'manual_submitted_at',
        'manual_note',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_paisa' => 'integer',
            'tax_paisa' => 'integer',
            'total_paisa' => 'integer',
            'sikka_amount' => 'integer',
            'meta' => 'array',
            'paid_at' => 'datetime',
            'manual_submitted_at' => 'datetime',
        ];
    }

    /** True when this order rides the Sikka rail (balance IS the payment). */
    public function isSikkaRail(): bool
    {
        return $this->currency === self::CURRENCY_SIKKA;
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Membership plan ids carried by this order's lines (S5 rail). */
    public function membershipPlanIds(): Collection
    {
        return $this->items()->whereNotNull('membership_plan_id')->pluck('membership_plan_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Transition to a new status. Orders are financial history — they are
     * never deleted, only moved through this state machine (AGENTS.md #5).
     */
    public function transitionTo(string $status): void
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_PAID, self::STATUS_FAILED],
            self::STATUS_PAID => [self::STATUS_REFUNDED],
            self::STATUS_FAILED => [self::STATUS_PENDING], // retry allowed
            self::STATUS_REFUNDED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \DomainException("Illegal order transition: {$this->status} → {$status}");
        }

        $this->status = $status;
        $this->save();
    }
}
