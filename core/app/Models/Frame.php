<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    /**
     * H3 (v1.7.3 hotfix 2) — the frame's PUBLIC URL, resolved in ONE place.
     *
     * The column is `image_path` (NOT `path`): it holds the public-disk
     * relative path written by the ImageUploadService 'frame' variant.
     * Views used to hand-roll `Storage::disk('public')->url($frame->image_path)`
     * inline, which is how a "buried frame" bug hides — a mistyped disk or
     * path silently rendered an empty src with no error. `$frame->url` is now
     * the single accessor every surface reads, and the URL is non-empty for
     * any frame that actually has an image (locked by FrameTruthTest).
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : '',
        );
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
