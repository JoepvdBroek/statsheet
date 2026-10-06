<?php

namespace Tests\Feature;

use App\Ai\Agents\WeeklyReviewer;
use App\Enums\Muscle;
use App\Enums\ReviewRating;
use App\Enums\ReviewStatus;
use App\Jobs\GenerateWeeklyReview;
use App\Models\Goal;
use App\Models\WeeklyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

class GenerateWeeklyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_generation_stores_the_three_parts_and_the_model()
    {
        config(['ai.providers.openai.models.text.default' => 'gpt-review']);
        $this->travelTo('2026-09-28 06:00:00');
        $review = WeeklyReview::factory()->pending()->create(['week' => '2026-09-21']);
        Goal::factory()->for($review->user)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-07']);
        WeeklyReviewer::fake([[
            'summary' => 'Four Workouts and a new bench record.',
            'muscle_notes' => [['muscle' => 'chest', 'note' => 'Met: 6,200 kg against 5,000 kg.']],
            'advice' => ['Keep the fourth Set of bench.', 'Add a Set of rows.'],
        ]])->preventStrayPrompts();

        GenerateWeeklyReview::dispatchSync($review);

        $review->refresh();
        $this->assertSame(ReviewStatus::Done, $review->status);
        $this->assertSame('Four Workouts and a new bench record.', $review->summary);
        $this->assertSame([['muscle' => 'chest', 'note' => 'Met: 6,200 kg against 5,000 kg.']], $review->muscle_notes);
        $this->assertSame(['Keep the fourth Set of bench.', 'Add a Set of rows.'], $review->advice);
        $this->assertSame('gpt-review', $review->model);
        $this->assertSame('2026-09-28 06:00:00', $review->generated_at->toDateTimeString());
        WeeklyReviewer::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('2026-09-21') && $prompt->contains('2026-09-14'));
    }

    public function test_only_one_note_per_muscle_with_a_goal_in_force_that_week_is_stored()
    {
        $review = WeeklyReview::factory()->pending()->create(['week' => '2026-09-21']);
        Goal::factory()->for($review->user)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-07']);
        Goal::factory()->for($review->user)->create(['muscle' => Muscle::Lats, 'weekly_minimum' => '3000.00', 'effective_week' => '2026-09-28']);
        WeeklyReviewer::fake([[
            'summary' => 'A Week.',
            'muscle_notes' => [
                ['muscle' => 'chest', 'note' => 'Met.'],
                ['muscle' => 'lats', 'note' => 'No Goal yet that Week.'],
                ['muscle' => 'chest', 'note' => 'Met again.'],
            ],
            'advice' => [],
        ]])->preventStrayPrompts();

        GenerateWeeklyReview::dispatchSync($review);

        $this->assertSame([['muscle' => 'chest', 'note' => 'Met.']], $review->refresh()->muscle_notes);
        WeeklyReviewer::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('chest 5000'));
    }

    public function test_a_failed_generation_keeps_the_previous_content_and_marks_the_review_failed()
    {
        $this->travelTo('2026-09-28 06:00:00');
        $review = WeeklyReview::factory()->create([
            'week' => '2026-09-21',
            'status' => ReviewStatus::Pending,
            'summary' => 'The earlier review.',
            'advice' => ['Sleep more.'],
            'generated_at' => '2026-09-27 20:00:00',
        ]);
        WeeklyReviewer::fake(fn () => throw new RuntimeException('The provider is down.'))->preventStrayPrompts();

        GenerateWeeklyReview::dispatchSync($review);

        $review->refresh();
        $this->assertSame(ReviewStatus::Failed, $review->status);
        $this->assertSame('The earlier review.', $review->summary);
        $this->assertSame(['Sleep more.'], $review->advice);
        $this->assertSame('2026-09-27 20:00:00', $review->generated_at->toDateTimeString());
    }

    public function test_a_regeneration_clears_the_rating_of_the_old_text()
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Down, 'Too vague.')->create(['status' => ReviewStatus::Pending]);
        WeeklyReviewer::fake([[
            'summary' => 'A sharper review.',
            'muscle_notes' => [],
            'advice' => ['Add a Set of rows.'],
        ]])->preventStrayPrompts();

        GenerateWeeklyReview::dispatchSync($review);

        $review->refresh();
        $this->assertSame('A sharper review.', $review->summary);
        $this->assertNull($review->rating);
        $this->assertNull($review->rating_comment);
    }

    public function test_a_failed_regeneration_keeps_the_rating()
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Up, 'Spot on.')->create(['status' => ReviewStatus::Pending]);
        WeeklyReviewer::fake(fn () => throw new RuntimeException('The provider is down.'))->preventStrayPrompts();

        GenerateWeeklyReview::dispatchSync($review);

        $review->refresh();
        $this->assertSame(ReviewRating::Up, $review->rating);
        $this->assertSame('Spot on.', $review->rating_comment);
    }

    public function test_without_an_openai_key_a_generation_fails_and_can_be_retried()
    {
        config(['ai.providers.openai.key' => null]);
        Http::preventStrayRequests();
        $review = WeeklyReview::factory()->pending()->create();

        GenerateWeeklyReview::dispatchSync($review);

        $this->assertSame(ReviewStatus::Failed, $review->refresh()->status);
    }

    public function test_a_generation_the_queue_gives_up_on_marks_the_review_failed()
    {
        $review = WeeklyReview::factory()->pending()->create();

        (new GenerateWeeklyReview($review))->failed(new RuntimeException('Timed out.'));

        $this->assertSame(ReviewStatus::Failed, $review->refresh()->status);
    }
}
