<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prompt_id' => Prompt::factory(),
            'price_paisa' => 50_000, // NPR 500.00
            'compare_at_price_paisa' => null,
            'status' => Product::STATUS_ACTIVE,
            'currency' => 'NPR',
        ];
    }

    public function priced(int $paisa): static
    {
        return $this->state(fn () => ['price_paisa' => $paisa]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Product::STATUS_DRAFT]);
    }
}
