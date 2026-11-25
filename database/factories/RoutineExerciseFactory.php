<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineExercise>
 */
class RoutineExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'exercise_id' => fn (array $attributes) => Exercise::factory()->create([
                'user_id' => Routine::whereKey($attributes['routine_id'])->firstOrFail()->user_id,
            ]),
            'position' => 0,
            'superset' => null,
        ];
    }

    /**
     * Indicate that the Exercise belongs to the Routine's Superset with the given number.
     */
    public function inSuperset(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'superset' => $number,
        ]);
    }
}
