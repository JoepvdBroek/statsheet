<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Workout>
 */
class WorkoutFactory extends Factory
{
    /**
     * Define the model's default state: in progress, without Exercises.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'started_at' => now(),
            'finished_at' => null,
            'bodyweight' => null,
            'note' => null,
        ];
    }

    /**
     * Indicate that the Workout performs the given number of the owner's Exercises, each with the given number of not-done Sets.
     */
    public function withExercises(int $exerciseCount = 2, int $setCount = 3): static
    {
        return $this->has(
            WorkoutExercise::factory()
                ->count($exerciseCount)
                ->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])
                ->has(
                    WorkoutSet::factory()
                        ->count($setCount)
                        ->sequence(fn (Sequence $sequence) => ['position' => $sequence->index % $setCount]),
                    'sets',
                ),
            'exercises',
        );
    }

    /**
     * Indicate that the Workout has been finished, an hour after it started.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'finished_at' => Date::parse($attributes['started_at'])->addHour(),
        ]);
    }
}
