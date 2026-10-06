<?php

namespace Database\Factories;

use App\Enums\Muscle;
use App\Enums\ReviewRating;
use App\Enums\ReviewStatus;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyReview>
 */
class WeeklyReviewFactory extends Factory
{
    /**
     * Define the model's default state: a done review of last Week in UTC.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week' => now()->subWeek()->startOfWeek()->toDateString(),
            'status' => ReviewStatus::Done,
            'summary' => fake()->paragraph(),
            'muscle_notes' => [['muscle' => fake()->randomElement(Muscle::cases())->value, 'note' => fake()->sentence()]],
            'advice' => [fake()->sentence(), fake()->sentence()],
            'model' => 'gpt-test',
            'generated_at' => now(),
        ];
    }

    /**
     * Indicate that the review is being generated for the first time, so it has no content yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Pending,
            'summary' => null,
            'muscle_notes' => null,
            'advice' => null,
            'model' => null,
            'generated_at' => null,
        ]);
    }

    /**
     * Indicate that the latest generation failed, keeping any earlier content.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Failed,
        ]);
    }

    /**
     * Indicate that the owner has rated the review.
     */
    public function rated(ReviewRating $rating, ?string $comment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
            'rating_comment' => $comment,
        ]);
    }
}
