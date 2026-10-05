<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * W4 (v1.7.3) — the frame award ledger. A user holds a frame at most once
 * (UNIQUE(user, frame)); rows record the audit trail (who granted, why).
 * Automatic criteria grant unlocks implicitly through the evaluator — no
 * row is required for the criterion itself, but manual awards MUST have
 * one (granted_by + mandatory reason).
 */
class UserFrameUnlock extends Model
{
    public const SOURCE_MANUAL = 'manual';

    /** S5 (v1.8.0): a membership perk granted the unlock (reason carries
     *  `membership:{plan slug}`). */
    public const SOURCE_MEMBERSHIP = 'membership';

    protected $fillable = [
        'user_id',
        'frame_id',
        'source',
        'granted_by',
        'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
