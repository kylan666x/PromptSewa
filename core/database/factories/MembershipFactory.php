<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => MembershipPlan::factory(),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'status' => Membership::STATUS_ACTIVE,
            'source_order_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Membership::STATUS_EXPIRED,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
        ]);
    }

    /** Active row whose ends_at has already passed — the expiry sweep's target. */
    public function lapsed(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(1),
        ]);
    }
}
