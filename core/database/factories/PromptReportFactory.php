<?php

namespace Database\Factories;

use App\Models\Prompt;
use App\Models\PromptReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptReport>
 */
class PromptReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prompt_id' => Prompt::factory(),
            'user_id' => fake()->boolean(50) ? User::factory() : null,
            'reason' => fake()->randomElement(array_keys(PromptReport::REASONS)),
            'message' => fake()->paragraph(),
            'reporter_email' => fake()->boolean(25) ? fake()->safeEmail() : null,
            'status' => PromptReport::STATUS_OPEN,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }

    /** Triage states — resolved/dismissed stamp who and when. */
    public function resolved(): static
    {
        return $this->afterCreating(function (PromptReport $report) {
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

            $report->forceFill([
                'status' => PromptReport::STATUS_RESOLVED,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();
        });
    }

    public function dismissed(): static
    {
        return $this->afterCreating(function (PromptReport $report) {
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

            $report->forceFill([
                'status' => PromptReport::STATUS_DISMISSED,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ])->save();
        });
    }

    public function reason(string $reason): static
    {
        return $this->state(fn () => ['reason' => $reason]);
    }
}
