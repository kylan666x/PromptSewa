<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T3 (v1.5.0): admin account-switching session log.
 *
 * One row per impersonation session; `ended_at` updates on return. This
 * is an audit/session log, NOT a financial ledger — updating ended_at is
 * allowed. Every start AND end writes (or updates) a row.
 */
class Impersonation extends Model
{
    protected $fillable = [
        'impersonator_id',
        'target_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /** The admin's active (un-ended) impersonation of anyone, if any. */
    public static function activeBy(int $impersonatorId): ?self
    {
        return static::query()
            ->where('impersonator_id', $impersonatorId)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }
}
