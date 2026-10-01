<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G5 (v1.7.0) — per-prompt per-day view counter. UNIQUE(prompt, day) makes
 * the AnalyticsService upsert idempotent. Views are STATS, not money —
 * upserts allowed; the wallet ledger stays insert-only.
 */
class PromptDailyStat extends Model
{
    public $timestamps = false;

    protected $fillable = ['prompt_id', 'day', 'views'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'views' => 'integer',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
