<?php

namespace Database\Factories;

use App\Enums\Muscle;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * Define the model's default state: a Goal in force from this Week in UTC.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'muscle' => fake()->randomElement(Muscle::cases()),
            'weekly_minimum' => fake()->numberBetween(10, 100) * 100,
            'effective_week' => now()->startOfWeek()->toDateString(),
        ];
    }

    /**
     * Indicate that this version removes the Goal.
     */
    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'weekly_minimum' => null,
        ]);
    }
}
