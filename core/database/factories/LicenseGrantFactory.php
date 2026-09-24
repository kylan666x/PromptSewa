<?php

namespace Database\Factories;

use App\Models\LicenseGrant;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LicenseGrant>
 */
class LicenseGrantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_item_id' => OrderItem::factory(),
            'prompt_id' => fn (array $attrs) => OrderItem::find($attrs['order_item_id'])->prompt_id,
            'license_tier' => 'personal',
            'grant_code' => strtoupper(Str::random(12)).'-'.Str::random(8),
            'status' => LicenseGrant::STATUS_ACTIVE,
        ];
    }
}
