<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable snapshot of a prompt's content (a "commit").
 * Never mutate rows here — history must stay append-only.
 */
class PromptVersion extends Model
{
    final public const STATUS_DRAFT = 'draft';

    final public const STATUS_PENDING = 'pending';

    final public const STATUS_PUBLISHED = 'published';

    final public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'prompt_id',
        'version_number',
        'body',
        'changelog',
        'variables',
        'tags',
        'recommended_tools',
        'audience',
        'tips',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'tags' => 'array',
            'recommended_tools' => 'array',
            'tips' => 'array',
        ];
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** UI label: "v3". */
    public function label(): string
    {
        return "v{$this->version_number}";
    }

    /**
     * Fill placeholders a creator can type into, extracted from the
     * {{variable}} tokens in the body (God of Prompt "fill in the
     * variables" pattern).
     *
     * @return list<string>
     */
    public function variableNames(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_ -]+?)\s*\}\}/', $this->body, $matches);

        return collect($matches[1])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function toolList(): array
    {
        return array_values((array) $this->recommended_tools);
    }

    /** @return list<string> */
    public function tipList(): array
    {
        return array_values((array) $this->tips);
    }

    /** Suggested input placeholder for a variable name in the create form. */
    public static function placeholderFor(string $variable): string
    {
        return 'Your '.str_replace(['_', '-'], ' ', strtolower($variable)).'…';
    }
}
