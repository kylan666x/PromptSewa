<?php

namespace App\Models;

use App\Services\SettingsService;
use App\Support\SikkaFormat;
use Database\Factories\PackFactory;
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
    /** @use HasFactory<PackFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tagline',
        'hero_copy',
        'price_sikka',
        'price_paisa',
        'currency',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'price_sikka' => 'integer',
            'price_paisa' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * S1b (v1.9.0): Sikka is the pack price of record and price_paisa is
     * the derived NPR mirror — the exact contract prompts already carry.
     * The pair stays consistent at save: a Sikka write derives the paisa
     * mirror (× buy rate), a legacy paisa write (factories, seeders,
     * pre-Sikka code) derives the Sikka price rounding UP so a paid pack
     * can never look free, and both-dirty writes are honored as-is (the
     * migration backfill). Nothing here reads NPR to decide a Sikka price.
     */
    protected static function booted(): void
    {
        static::saving(function (Pack $pack): void {
            $sikkaDirty = $pack->isDirty('price_sikka');
            $paisaDirty = $pack->isDirty('price_paisa');

            if (! $sikkaDirty && ! $paisaDirty) {
                return;
            }

            $buy = $pack->buyRatePaisa();

            if ($sikkaDirty && ! $paisaDirty) {
                $pack->price_paisa = max(0, (int) $pack->price_sikka) * $buy;
            } elseif ($paisaDirty && ! $sikkaDirty) {
                $paisa = max(0, (int) $pack->price_paisa);
                $pack->price_sikka = $paisa === 0 ? 0 : intdiv($paisa + $buy - 1, $buy);
            }
        });
    }

    /** The configured buy rate in paisa per Sikka (default 100 = NPR 1). */
    public function buyRatePaisa(): int
    {
        $value = (int) app(SettingsService::class)->get('sikka_buy_paisa_per_token', '100');

        return $value > 0 ? $value : 100;
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

    /** S1b (v1.9.0): the pack price of record — integer credits, 0 = free. */
    public function priceSikka(): int
    {
        return max(0, (int) $this->price_sikka);
    }

    public function isFree(): bool
    {
        return $this->priceSikka() === 0;
    }

    /**
     * The derived NPR mirror in major units — the real-money value of the
     * pack, used for structured data (schema.org needs a real currency) and
     * the admin desk. NEVER rendered on a buyer surface: those price
     * through <x-sikka> exclusively.
     */
    public function priceNpr(): int
    {
        return intdiv($this->price_paisa, 100);
    }

    /**
     * Plain-text label for admin/structured-data contexts. Buyer surfaces
     * must render <x-sikka :amount="$pack->priceSikka()"/> instead — this
     * string is not the display contract.
     */
    public function priceLabel(): string
    {
        return $this->isFree()
            ? 'Free'
            : SikkaFormat::render($this->priceSikka()).' Sikka';
    }
}
