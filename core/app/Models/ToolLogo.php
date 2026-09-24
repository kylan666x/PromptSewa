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
    protected $fillable = ['name', 'logo_path', 'position', 'is_active'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
