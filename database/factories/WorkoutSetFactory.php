<?php

namespace Database\Factories;

use App\Enums\SetKind;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    /**
     * Define the model's default state: a working Set with a Target, not done yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_exercise_id' => WorkoutExercise::factory(),
            'position' => 0,
            'target_reps' => fake()->numberBetween(5, 12),
            'target_weight' => fake()->numberBetween(4, 60) * 2.5,
            'actual_reps' => null,
            'actual_weight' => null,
            'kind' => SetKind::Working,
        ];
    }

    /**
     * Indicate that the Set was done as planned: its Actual equals its Target.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_reps' => $attributes['target_reps'] ?? fake()->numberBetween(5, 12),
            'actual_weight' => $attributes['target_weight'] ?? fake()->numberBetween(4, 60) * 2.5,
        ]);
    }

    /**
     * Indicate that the Set was done but missed its Target by two reps.
     */
    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_reps' => $attributes['target_reps'] - 2,
            'actual_weight' => $attributes['target_weight'],
        ]);
    }

    /**
     * Indicate that the Set was not done: it has no Actual.
     */
    public function notDone(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_reps' => null,
            'actual_weight' => null,
        ]);
    }

    /**
     * Indicate that the Set has no Target, as in an ad-hoc Workout.
     */
    public function withoutTarget(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_reps' => null,
            'target_weight' => null,
        ]);
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
