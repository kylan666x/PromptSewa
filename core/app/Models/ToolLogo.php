<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed AI tool registry (ChatGPT, Gemini, Midjourney, …) with
 * optional logos. Shown on prompt cards/detail pages instead of bare text
 * chips when a logo exists.
 */
class ToolLogo extends Model
{
    /** Modality: which prompt types this tool serves. 'any' fits all. */
    final public const MODALITIES = ['text', 'image', 'video', 'agentic', 'skill', 'any'];

    final public const MODALITY_ANY = 'any';

    /** True when this tool can back a prompt of the given type. */
    public function serves(string $type): bool
    {
        return $this->modality === self::MODALITY_ANY || $this->modality === $type;
    }

    protected $fillable = ['name', 'logo_path', 'position', 'is_active', 'modality'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
