<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A 1-5 star rating on a prompt. One row per (user, prompt) — re-rating
 * updates the existing row. Aggregates render wherever prompt stats do.
 */
class Rating extends Model
{
    public const MIN_SCORE = 1;
    public const MAX_SCORE = 5;

    protected $fillable = ['user_id', 'prompt_id', 'score'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
