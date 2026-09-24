<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    final public const STATUS_PENDING = 'pending';

    final public const STATUS_PAID = 'paid';

    final public const STATUS_FAILED = 'failed';

    final public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'buyer_id',
        'status',
        'subtotal_paisa',
        'tax_paisa',
        'total_paisa',
        'currency',
        'idempotency_key',
        'payment_method',
        'payment_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_paisa' => 'integer',
            'tax_paisa' => 'integer',
            'total_paisa' => 'integer',
            'paid_at' => 'datetime',
        ];
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
