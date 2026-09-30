<?php

namespace Database\Factories;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Enums\MuscleRole;
use App\Models\Exercise;
use App\Models\ExerciseMuscle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
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
            'name' => ucfirst(fake()->word()).' '.fake()->word(),
            'equipment' => fake()->randomElement(Equipment::cases()),
            'is_bodyweight' => false,
            'source_id' => null,
            'archived_at' => null,
        ];
    }

    /**
     * Give every created Exercise a random primary Muscle unless a state chose one.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Exercise $exercise) {
            if (! $exercise->muscles()->where('role', MuscleRole::Primary)->exists()) {
                ExerciseMuscle::factory()->for($exercise)->create();
            }
        });
    }

    /**
     * Indicate that the Exercise trains the given Muscle as its primary Muscle.
     */
    public function primaryMuscle(Muscle $muscle): static
    {
        return $this->has(ExerciseMuscle::factory()->state(['muscle' => $muscle]), 'muscles');
    }

    /**
     * Indicate that the Exercise also trains the given Muscle, or a random other one, as a secondary Muscle.
     */
    public function withSecondaryMuscle(?Muscle $muscle = null): static
    {
        return $this->afterCreating(function (Exercise $exercise) use ($muscle) {
            $trained = $exercise->muscles()->pluck('muscle')->all();

            ExerciseMuscle::factory()->for($exercise)->secondary()->create([
                'muscle' => $muscle ?? fake()->randomElement(
                    array_filter(Muscle::cases(), fn (Muscle $candidate) => ! in_array($candidate, $trained, true)),
                ),
            ]);
        });
    }

    /**
     * Indicate that the Exercise is a Bodyweight Exercise.
     */
    public function bodyweight(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bodyweight' => true,
        ]);
    }

    /**
     * Indicate that the Exercise has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
