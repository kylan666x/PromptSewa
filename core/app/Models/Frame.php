<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * G3 (v1.7.0) — a cosmetic avatar ring. image_path is an alpha-PNG frame
 * variant (512px, transparency required). Removing a frame nulls
 * users.active_frame_id (nullOnDelete) — never breaks a user row.
 */
class Frame extends Model
{
    /** W3 (v1.7.3): decorative CSS motion classes for the ring overlay. */
    public const ANIMATIONS = ['none', 'spin', 'pulse', 'shine'];

    /** W4 (v1.7.3): the criterion literal for admin-granted frames. */
    public const CRITERION_MANUAL = 'manual';

    protected $fillable = [
        'name',
        'image_path',
        'is_active',
        'criterion',
        'animation',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Free frame: null criterion (anyone may equip it). */
    public function isFree(): bool
    {
        return $this->criterion === null;
    }

    /** Does this user hold an unlock row for this frame? */
    public function isUnlockedBy(\App\Models\User $user): bool
    {
        if ($this->isFree()) {
            return true;
        }

        return $this->unlocks()->where('user_id', $user->id)->exists();
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(\App\Models\UserFrameUnlock::class);
    }
}
