<?php

namespace App\Models;

use Database\Factories\SikkaPackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * S1 (v1.8.0) — an admin-managed Sikka top-up pack.
 *
 * Packs are sold through the ordinary NPR rails; on approval
 * SikkaService::topupCredit credits sikka_amount + bonus_sikka as two
 * ledger rows (the bonus auditable on its own).
 */
class SikkaPack extends Model
{
    /** @use HasFactory<SikkaPackFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sikka_amount',
        'bonus_sikka',
        'price_paisa',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'sikka_amount' => 'integer',
            'bonus_sikka' => 'integer',
            'price_paisa' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** Total Sikka credited by one unit of this pack. */
    public function totalSikka(): int
    {
        return $this->sikka_amount + $this->bonus_sikka;
    }

    /** S6 (v1.8.0): the order lines that sold this pack (restrictOnDelete
     *  keeps a sold pack's row for audit; the desk deactivates instead). */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'sikka_pack_id');
    }
}
