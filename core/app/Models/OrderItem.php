<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'pack_id',
        'membership_plan_id',
        'sikka_pack_id',
        'prompt_id',
        'price_paisa',
        'currency',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'price_paisa' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function licenseGrant(): HasOne
    {
        return $this->hasOne(LicenseGrant::class);
    }

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class);
    }

    /** S5 (v1.8.0): membership plan lines ride the ordinary order pipeline. */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    /** S2 (v1.8.0): Sikka top-up lines — credited on approval. */
    public function sikkaPack(): BelongsTo
    {
        return $this->belongsTo(SikkaPack::class, 'sikka_pack_id');
    }

    /** Line total in paisa (integer math only). */
    public function lineTotalPaisa(): int
    {
        return $this->price_paisa * $this->quantity;
    }

    /**
     * F1 (v1.9.2) — THE definition of a sale line: its order reached PAID.
     *
     * Both rails settle the SAME order rows — the NPR rail (eSewa / manual)
     * and the Sikka credit rail (SikkaService::spendSikka flips the order to
     * paid) — so counting paid lines is rail-agnostic by construction.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->whereHas('order', fn (Builder $q) => $q->where('status', Order::STATUS_PAID));
    }

    /** F1 (v1.9.2) — paid lines that name one of these prompts directly. */
    public function scopeForPromptIds(Builder $query, iterable $promptIds): Builder
    {
        return $query->paid()->whereIn('prompt_id', collect($promptIds)->all());
    }

    /**
     * F1 (v1.9.2) — how many sales a creator has actually made.
     *
     * The bug this replaces: every sales surface summed `prompts.sales_count`,
     * a denormalized column NOTHING in the codebase ever increments (no
     * `increment('sales_count')` exists repo-wide), so a creator who had sold
     * prompts on the credit rail still read 0 sales on the dashboard and the
     * public profile.
     *
     * Comps are excluded by construction (a comp grant creates no order
     * line); soft-deleted listings are included — the sale happened, the
     * count is an accounting fact.
     */
    public static function paidSalesCountForCreator(User $creator): int
    {
        $promptIds = Prompt::withTrashed()->where('user_id', $creator->id)->pluck('id');

        if ($promptIds->isEmpty()) {
            return 0;
        }

        return (int) static::query()->forPromptIds($promptIds)->count();
    }

    /** F1 (v1.9.2) — the same truth for ONE listing (milestones, per-card numbers). */
    public static function paidSalesCountForPrompt(Prompt $prompt): int
    {
        return (int) static::query()->forPromptIds([$prompt->id])->count();
    }
}
