<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'prompt_id' => fn (array $attrs) => Product::find($attrs['product_id'])->prompt_id ?? Prompt::factory(),
            'price_paisa' => 50_000,
            'currency' => 'NPR',
            'quantity' => 1,
        ];
    }
}
