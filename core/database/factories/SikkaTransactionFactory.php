<?php

namespace Database\Factories;

use App\Models\SikkaTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SikkaTransaction>
 */
class SikkaTransactionFactory extends Factory
{
    protected $model = SikkaTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SikkaTransaction::TYPE_ADMIN_GRANT,
            'amount_sikka' => 100,
            'cashout_eligible' => false,
            'idempotency_key' => 'grant:factory-'.fake()->unique()->numerify('######'),
            'meta' => ['reason' => 'factory'],
            'created_at' => now(),
        ];
    }

    /** Withdrawable row (a top-up-shaped credit). */
    public function eligible(): static
    {
        return $this->state(fn () => [
            'type' => SikkaTransaction::TYPE_TOPUP,
            'cashout_eligible' => true,
        ]);
    }
}
