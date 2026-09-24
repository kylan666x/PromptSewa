<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Prompt>
 */
class PromptFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'search_text' => $title.' '.fake()->words(10, true),
            'license_tier' => Prompt::LICENSE_PERSONAL,
            'price_cents' => 0,
            'status' => Prompt::STATUS_PUBLISHED,
            'visibility' => Prompt::VISIBILITY_PUBLIC,
            'type' => fake()->randomElement(Prompt::TYPES),
        ];
    }

    /** Attach a published v1 with a realistic body, tags, tools, tips. */
    public function withVersion(): static
    {
        return $this->afterCreating(function (Prompt $prompt) {
            $tags = fake()->randomElements(
                ['marketing', 'seo', 'coding', 'design', 'writing', 'video', 'analysis'],
                3
            );

            $prompt->versions()->create([
                'version_number' => 1,
                'body' => "You are an expert {$prompt->category?->name} assistant.\n\nTask: {{task}}.\nAudience: {{audience}}.\n\nDeliver a structured result in markdown, no preamble.",
                'changelog' => 'Initial release.',
                'tags' => $tags,
                'recommended_tools' => fake()->randomElements(['ChatGPT', 'Claude', 'Midjourney', 'DALL-E', 'Sora', 'Gemini'], 2),
                'audience' => fake()->randomElement(['Marketers', 'Developers', 'Designers', 'Founders', 'Content creators']),
                'tips' => [
                    'Be specific in the {{task}} variable — vague inputs produce vague outputs.',
                    'Run it twice and merge the strongest sections.',
                ],
                'user_id' => $prompt->user_id,
                'status' => PromptVersion::STATUS_PUBLISHED,
            ]);
        });
    }

    /** Deterministic v1 for tests that assert on exact body/tags/tools. */
    public function hasVersion(): static
    {
        return $this->afterCreating(function (Prompt $prompt) {
            $prompt->versions()->create([
                'version_number' => 1,
                'body' => "A deterministic body for {$prompt->slug} featuring the {{topic}} variable.",
                'changelog' => 'Initial release.',
                'tags' => ['probe', 'test'],
                'recommended_tools' => ['ChatGPT', 'Claude'],
                'audience' => 'Testers',
                'tips' => ['First deterministic tip', 'Second deterministic tip'],
                'user_id' => $prompt->user_id,
                'status' => PromptVersion::STATUS_PUBLISHED,
            ]);
        });
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => Prompt::STATUS_PUBLISHED]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Prompt::STATUS_DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => Prompt::STATUS_PENDING]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['visibility' => Prompt::VISIBILITY_PRIVATE]);
    }

    public function priced(int $paisa): static
    {
        return $this->state(fn () => ['price_cents' => $paisa]);
    }

    public function commercial(): static
    {
        return $this->state(fn () => ['license_tier' => Prompt::LICENSE_COMMERCIAL]);
    }
}
