<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' plan',
            'slug' => Str::slug(fake()->unique()->words(3, true)),
            'duration_days' => 30,
            'price_paisa' => 50_000,
            'stipend_sikka' => 0,
            'perks' => [
                MembershipPlan::PERK_UNLIMITED_UNLOCK => false,
                MembershipPlan::PERK_BADGE_ID => null,
                MembershipPlan::PERK_FRAME_ID => null,
                MembershipPlan::PERK_GRANT_VERIFIED => false,
            ],
            'active' => true,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn () => [
            'perks' => array_merge($this->definition()['perks'], [
                MembershipPlan::PERK_UNLIMITED_UNLOCK => true,
            ]),
        ]);
    }
}
