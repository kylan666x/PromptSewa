<?php

namespace Database\Factories;

use App\Models\SikkaPack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SikkaPack>
 */
class SikkaPackFactory extends Factory
{
    protected $model = SikkaPack::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 2000);

        return [
            'name' => fake()->words(2, true).' pack',
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'sikka_amount' => $amount,
            'bonus_sikka' => 0,
            'price_paisa' => $amount * 100, // default buy rate 1:1 NPR
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
