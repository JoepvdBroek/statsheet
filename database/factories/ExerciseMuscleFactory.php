<?php

namespace Database\Factories;

use App\Enums\Muscle;
use App\Enums\MuscleRole;
use App\Models\ExerciseMuscle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseMuscle>
 */
class ExerciseMuscleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'muscle' => fake()->randomElement(Muscle::cases()),
            'role' => MuscleRole::Primary,
        ];
    }

    /**
     * Indicate that the Exercise trains this Muscle as a secondary Muscle.
     */
    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => MuscleRole::Secondary,
        ]);
    }
}
