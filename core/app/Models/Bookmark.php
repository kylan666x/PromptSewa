<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T13 (v1.5.0): a user's saved prompt. Unique (user_id, prompt_id) — the
 * toggle endpoint upserts/deletes, never duplicates.
 */
class Bookmark extends Model
{
    /** @use HasFactory<\Database\Factories\BookmarkFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'prompt_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** True when the given user has saved the given prompt. */
    public static function isSaved(int $userId, int $promptId): bool
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('prompt_id', $promptId)
            ->exists();
    }
}
