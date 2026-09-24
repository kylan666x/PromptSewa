<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A pack is a curated bundle of prompts sold for one price. Buying a pack
 * grants a license to every published prompt inside it (fulfilled through
 * EntitlementService as individual LicenseGrants).
 */
class Pack extends Model
{
    /** @use HasFactory<\Database\Factories\PackFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_paisa',
        'currency',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'price_paisa' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'pack_prompt');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Published prompts only — what a buyer actually receives today. */
    public function publishedPrompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'pack_prompt')->publicListing();
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::slug(Str::random(8));
        $slug = $base;
        $attempt = 1;

        while (self::query()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.(++$attempt);
        }

        return $slug;
    }

    /** Display helper: NPR major units (never used for math). */
    public function priceNpr(): int
    {
        return intdiv($this->price_paisa, 100);
    }

    public function priceLabel(): string
    {
        return $this->price_paisa === 0
            ? 'Free'
            : 'Rs. '.number_format($this->priceNpr());
    }
}
