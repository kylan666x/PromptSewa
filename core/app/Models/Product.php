<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    final public const STATUS_DRAFT = 'draft';

    final public const STATUS_ACTIVE = 'active';

    final public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'prompt_id',
        'price_paisa',
        'compare_at_price_paisa',
        'status',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'price_paisa' => 'integer',
            'compare_at_price_paisa' => 'integer',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Display helper: NPR major units (never used for math). */
    public function priceNpr(): int
    {
        return intdiv($this->price_paisa, 100);
    }
}
