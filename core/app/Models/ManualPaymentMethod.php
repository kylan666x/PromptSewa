<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * C3 (v1.4.4): an admin-configured manual payment method (bank transfer,
 * eSewa, Khalti…) with optional QR code + instructions shown at checkout.
 *
 * Orders snapshot the method NAME as a plain string — editing a method
 * never rewrites order history. "Deleting" a method that has been used
 * only deactivates it (name strings on old orders must stay meaningful);
 * hard delete is allowed only before first use.
 */
class ManualPaymentMethod extends Model
{
    /** P3 (v1.7.1): method kind — drives the checkout card's icon. */
    final public const KINDS = ['bank', 'esewa', 'other'];

    final public const KIND_BANK = 'bank';

    final public const KIND_ESEWA = 'esewa';

    final public const KIND_OTHER = 'other';

    protected $fillable = [
        'name',
        'kind',
        'instructions',
        'qr_path',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** Checkout-visible methods: active, ordered by position then name. */
    public function scopeOrderedForCheckout(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('position')->orderBy('name');
    }

    /** Any order (legacy payment_reference or v1.4.4 snapshot) uses this name? */
    public function isUsedByOrders(): bool
    {
        // Checkout snapshots store "NAME · reference" (manualSubmit), while
        // the legacy free-text field may equal the bare name. Match both
        // shapes — a plain equality on the snapshot column would miss every
        // real order and let a referenced method hard-delete.
        $escaped = str_replace('%', '\\%', $this->name);

        return Order::query()
            ->where(function ($q) use ($escaped) {
                $q->where('payment_reference', $this->name)
                    ->orWhere('payment_reference', 'like', $escaped.' · %')
                    ->orWhere('manual_txn_id', $this->name);
            })
            ->exists();
    }
}
