<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Routine>
 */
class RoutineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->word()).' day',
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the Routine plans the given number of the owner's Exercises, each with the given number of Sets.
     */
    public function withExercises(int $exerciseCount = 2, int $setCount = 3): static
    {
        return $this->has(
            RoutineExercise::factory()
                ->count($exerciseCount)
                ->sequence(fn (Sequence $sequence) => ['position' => $sequence->index])
                ->has(
                    RoutineSet::factory()
                        ->count($setCount)
                        ->sequence(fn (Sequence $sequence) => ['position' => $sequence->index]),
                    'sets',
                ),
            'exercises',
        );
    }

    /**
     * Indicate that the Routine has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
