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
            // S1 (v1.8.0): the rail marker — lowercase npr | sikka.
            'currency' => Order::CURRENCY_NPR,
            'sikka_amount' => 0,
            'idempotency_key' => Str::uuid()->toString(),
        ];
    }

    /** Order riding the Sikka rail: balance IS the payment. */
    public function sikka(int $amount): static
    {
        return $this->state(fn () => [
            'currency' => Order::CURRENCY_SIKKA,
            'sikka_amount' => $amount,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
