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
            'ChatGPT', 'Claude', 'Gemini', 'Midjourney',
            'DALL·E 3', 'Stable Diffusion', 'Ideogram', 'Flux',
            'Runway Gen-3', 'Sora', 'Kling', 'Luma',
        ];

        $position = 0;
        foreach ($tools as $name) {
            ToolLogo::query()->firstOrCreate(
                ['name' => $name],
                ['position' => ++$position, 'is_active' => true],
            );
        }
    }
}
