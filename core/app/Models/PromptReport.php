<?php

namespace App\Models;

use Database\Factories\PromptReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user (or guest) report against a prompt listing — abuse, theft,
 * broken prompt, etc. Admin triages: resolved (actioned) or dismissed.
 */
class PromptReport extends Model
{
    /** @use HasFactory<PromptReportFactory> */
    use HasFactory;

    final public const STATUS_OPEN = 'open';

    final public const STATUS_RESOLVED = 'resolved';

    final public const STATUS_DISMISSED = 'dismissed';

    /**
     * Stable reason keys — rendered as human labels via reasonLabel().
     * Keys only (no spaces) so they survive selects/URLs cleanly.
     */
    final public const REASONS = [
        'spam' => 'Spam or advertising',
        'stolen' => 'Stolen work / copyright',
        'broken' => 'Prompt does not work as described',
        'misleading' => 'Misleading description or price',
        'inappropriate' => 'Inappropriate or harmful content',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'prompt_id',
        'user_id',
        'reason',
        'message',
        'reporter_email',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
