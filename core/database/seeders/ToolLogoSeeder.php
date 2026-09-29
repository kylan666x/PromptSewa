<?php

namespace Database\Seeders;

use App\Models\ToolLogo;
use Illuminate\Database\Seeder;

/**
 * Seeds the default AI-tool registry (names only — logos are uploaded by
 * the admin from the panel). Idempotent: firstOrCreate on the unique name.
 */
class ToolLogoSeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            // T6 (v1.5.0): each name ships a default modality. The dash
            // spelling 'DALL-E' is added for back-compat with seeded/factory
            // data that references it; TaxonomySeeder owns the full registry.
            'ChatGPT' => 'text', 'Claude' => 'text', 'Gemini' => 'text',
            'Midjourney' => 'image',
            'DALL-E' => 'image', 'DALL·E 3' => 'image',
            'Stable Diffusion' => 'image', 'Ideogram' => 'image', 'Flux' => 'image',
            'Runway Gen-3' => 'video', 'Sora' => 'video', 'Kling' => 'video', 'Luma' => 'video',
        ];

        $position = 0;
        foreach ($tools as $name => $modality) {
            ToolLogo::query()->firstOrCreate(
                ['name' => $name],
                ['modality' => $modality, 'position' => ++$position, 'is_active' => true],
            );
        }
    }
}
