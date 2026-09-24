<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UI-001 demo marketplace content (users, categories, prompts).
        // Skips itself when prompts already exist — safe to re-run.
        $this->call(DemoContentSeeder::class);

        // Flagship creator account (idempotent — safe on every update).
        $this->call(JustShipItAISeeder::class);

        // Default AI-tool registry (ChatGPT, Gemini, …) for logo management.
        $this->call(ToolLogoSeeder::class);

        // A secondary test user for auth flows (kept from the skeleton).
        // firstOrCreate so re-seeding never collides on the unique email.
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );
    }
}
