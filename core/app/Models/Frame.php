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

    /**
     * v1.7.5 (R3) — the centre-hole tolerance, in ONE place.
     *
     * `hole_percent` is the diameter of the art's transparent centre as a
     * percentage of the 512px canvas; the photo layer is inset by
     * (100 - hole) / 2 on every side so it fills that hole exactly. 62 is
     * the shipped standard; 35 is the floor because the tightest measured
     * art (Abyssal) is 37.5% and anything below that stops reading as a
     * ring at card sizes.
     *
     * The admin form renders its min/max/step from THESE constants and the
     * model throws on an out-of-range save — a second hand-copied bound
     * would drift (the §6 hotfix-43 lesson).
     */
    public const HOLE_MIN = 35;

    public const HOLE_MAX = 70;

    public const HOLE_DEFAULT = 62;

    protected $fillable = [
        'name',
        'image_path',
        'is_active',
        'criterion',
        'animation',
        'hole_percent',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hole_percent' => 'integer',
        ];
    }

    /**
     * v1.7.5 (R3): a hole outside the tolerance is a geometry bug waiting to
     * happen (the photo would sit outside its ring), so the model refuses it
     * loudly rather than storing a value the component cannot honour.
     *
     * The default is applied HERE, not only by the column default: a row
     * created without an explicit value has the column default in the
     * database but a NULL attribute on the in-memory model until it is
     * refreshed, which is exactly how a surface ends up rendering an empty
     * data-frame-hole. Default-then-validate in one hook keeps the instance
     * and the row in agreement.
     */
    protected static function booted(): void
    {
        static::saving(function (self $frame): void {
            if ($frame->hole_percent === null || $frame->hole_percent === '') {
                $frame->hole_percent = self::HOLE_DEFAULT;
            }

            $hole = (int) $frame->hole_percent;

            if ($hole < self::HOLE_MIN || $hole > self::HOLE_MAX) {
                throw new \InvalidArgumentException(sprintf(
                    'Frame hole_percent must be between %d and %d (got %d).',
                    self::HOLE_MIN,
                    self::HOLE_MAX,
                    $hole,
                ));
            }
        });
    }

    /** The frame's hole, never null — the standard when unset. */
    public function holePercent(): int
    {
        return (int) ($this->hole_percent ?? self::HOLE_DEFAULT);
    }

    /**
     * The photo layer's inset, in percent, for this frame's hole. The exact
     * percent is emitted as an inline style — the sanctioned dynamic escape
     * (no arbitrary Tailwind class can exist per-frame at build time).
     */
    public function photoInsetPercent(): float
    {
        return round((100 - $this->holePercent()) / 2, 2);
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
