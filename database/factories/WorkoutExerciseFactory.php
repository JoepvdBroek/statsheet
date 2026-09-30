<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutExercise>
 */
class WorkoutExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'exercise_id' => fn (array $attributes) => Exercise::factory()->create([
                'user_id' => Workout::whereKey($attributes['workout_id'])->firstOrFail()->user_id,
            ]),
            'position' => 0,
        ];
    }
}
