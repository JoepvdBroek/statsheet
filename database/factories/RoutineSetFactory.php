<?php

namespace Database\Factories;

use App\Enums\SetKind;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineSet>
 */
class RoutineSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_exercise_id' => RoutineExercise::factory(),
            'position' => 0,
            'target_reps' => fake()->numberBetween(5, 12),
            'target_weight' => fake()->numberBetween(0, 60) * 2.5,
            'kind' => SetKind::Working,
        ];
    }

    /**
     * Indicate that the Set is a Warm-up Set.
     */
    public function warmUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SetKind::WarmUp,
        ]);
    }

    /**
     * Indicate that the Set is a Drop Set.
     */
    public function drop(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => SetKind::Drop,
        ]);
    }
}
