<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        // Post-v1.4.1 the column is NOT NULL — every user needs a handle.
        // Unique across the whole users table.
        //
        // The handle is TRUNCATED to the app's own 30-character rule: a
        // long faker name ("Mrs. Elouise Wisozk-Johnson") used to produce a
        // 34-character username, which the app itself refuses on every
        // profile save — so any test that round-tripped a factory handle
        // failed at random, depending on which name Faker rolled.
        $handle = Str::limit(Str::slug($name), 25, '').'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'name' => $name,
            'username' => $handle,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
