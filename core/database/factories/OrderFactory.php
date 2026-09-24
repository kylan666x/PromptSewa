<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'status' => Order::STATUS_PENDING,
            'subtotal_paisa' => 50_000,
            'tax_paisa' => 0,
            'total_paisa' => 50_000,
            'currency' => 'NPR',
            'idempotency_key' => Str::uuid()->toString(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
