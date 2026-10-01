<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G2 (v1.7.0) — a user's earned badge. UNIQUE(user, badge) is the award
 * idempotency gate; rows are never deleted (a revoked badge deactivates
 * the badge definition instead).
 */
class UserBadge extends Model
{
    protected $fillable = [
        'user_id',
        'badge_id',
        'awarded_by',
        'reason',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function awarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
