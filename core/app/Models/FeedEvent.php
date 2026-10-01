<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * G4 (v1.7.0) — one row in the news pulse. Written ONLY by the G2
 * observers inside the triggering transaction (never from controllers —
 * handoff §6 watch-out). banned actors are excluded at query level via
 * the publicStream scope.
 */
class FeedEvent extends Model
{
    public const TYPE_PROMPT_PUBLISHED = 'prompt_published';

    public const TYPE_SALE_MILESTONE = 'sale_milestone';

    public const TYPE_BADGE_EARNED = 'badge_earned';

    public const TYPE_PACK_CREATED = 'pack_created';

    public const TYPES = [
        self::TYPE_PROMPT_PUBLISHED,
        self::TYPE_SALE_MILESTONE,
        self::TYPE_BADGE_EARNED,
        self::TYPE_PACK_CREATED,
    ];

    public $timestamps = false;

    protected $fillable = [
        'type',
        'actor_id',
        'subject_type',
        'subject_id',
        'meta',
        'dedupe_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Public stream: banned actors excluded AT QUERY LEVEL (G4). */
    public function scopePublicStream($query)
    {
        return $query->whereHas('actor', fn ($q) => $q->whereNull('banned_at'))
            ->with(['actor', 'subject'])
            ->orderByDesc('id');
    }
}
