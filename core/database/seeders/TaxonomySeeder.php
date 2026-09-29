<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ToolLogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * T5/T6 (v1.5.0) — taxonomy truth: agentic/skill categories and the
 * modality-scoped tool registry.
 *
 * Production-safe and idempotent: firstOrCreate on unique keys, existing
 * rows are never modified (admin logo uploads and custom categories are
 * preserved). Safe to run on every deploy.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedTools();
    }

    private function seedCategories(): void
    {
        // [name, icon, typeScope, position] — slug-idempotent.
        $categories = [
            ['Agentic Workflows', '🤖', 'agentic', 100],
            ['Automation & Ops', '⚙️', 'agentic', 101],
            ['Skills & Frameworks', '🧩', 'skill', 102],
            ['Prompt Engineering', '📐', 'skill', 103],
        ];

        foreach ($categories as [$name, $icon, $typeScope, $position]) {
            Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'type_scope' => $typeScope,
                    'parent_id' => null,
                    'position' => $position,
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedTools(): void
    {
        // name => modality. 'any' = fits every prompt type.
        $tools = [
            // Text + agentic chat models (universal text tools keep 'any'
            // so legacy text prompts keep validating).
            'ChatGPT' => 'text',
            'Claude' => 'text',
            'Gemini' => 'text',
            'DeepSeek' => 'text',
            'Qwen' => 'text',
            'Grok' => 'text',
            'Mistral' => 'text',
            'Perplexity' => 'text',
            'Copilot' => 'any',

            // Image models.
            'Nano Banana' => 'image',
            'Midjourney' => 'image',
            'Flux' => 'image',
            'Leonardo' => 'image',
            'Ideogram' => 'image',

            // Video models.
            'Seedance 2.5' => 'video',
            'Runway' => 'video',
            'Pika' => 'video',
            'Veo' => 'video',
            'Kling' => 'video',

            // Agentic / skill frameworks.
            'CrewAI' => 'agentic',
            'AutoGPT' => 'agentic',
            'LangChain' => 'agentic',
            'n8n' => 'agentic',
        ];

        $position = (int) (ToolLogo::query()->max('position') ?? 0);

        foreach ($tools as $name => $modality) {
            $tool = ToolLogo::query()->firstOrCreate(
                ['name' => $name],
                ['modality' => $modality, 'position' => ++$position, 'is_active' => true],
            );

            // Backfill modality on rows created by the v1.4.5 ToolLogoSeeder
            // (they predate the column) without touching anything else.
            if ((string) $tool->modality !== $modality && $tool->wasRecentlyCreated === false && $tool->getOriginal('modality') === 'any') {
                $tool->forceFill(['modality' => $modality])->save();
            }
        }
    }
}
