<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Jobs\GenerateWeeklyReview;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeeklyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_asking_for_a_past_weeks_review_queues_its_generation()
    {
        Queue::fake();
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);

        $response = $this->actingAs($owner)->post(route('reviews.store'), ['week' => '2026-09-17']);

        $review = $owner->weeklyReviews()->sole();
        $response->assertRedirect(route('reviews.show', $review));
        $this->assertSame('2026-09-14', $review->week->toDateString());
        $this->assertSame(ReviewStatus::Pending, $review->status);
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job) => $job->review->is($review));
    }

    public function test_asking_for_the_current_weeks_review_follows_the_owners_timezone()
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:30:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);

        $this->actingAs($owner)->post(route('reviews.store'), ['week' => '2026-10-05'])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-05', $owner->weeklyReviews()->sole()->week->toDateString());
        Queue::assertPushed(GenerateWeeklyReview::class);
    }

    public function test_a_week_that_has_not_started_cannot_be_reviewed()
    {
        Queue::fake();
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('reviews.store'), ['week' => '2026-10-05']);

        $response->assertSessionHasErrors(['week' => 'This Week hasn\'t started yet.']);
        $this->assertDatabaseEmpty('weekly_reviews');
        Queue::assertNothingPushed();
    }

    public function test_asking_again_for_a_reviewed_week_keeps_its_content_until_the_new_one_is_in()
    {
        Queue::fake();
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $review = WeeklyReview::factory()->for($owner)->create(['week' => '2026-09-21', 'summary' => 'A solid Week.']);

        $response = $this->actingAs($owner)->post(route('reviews.store'), ['week' => '2026-09-27']);

        $response->assertRedirect(route('reviews.show', $review));
        $review->refresh();
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertSame('A solid Week.', $review->summary);
        $this->assertSame(1, $owner->weeklyReviews()->count());
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job) => $job->review->is($review));
    }

    public function test_asking_again_while_a_review_is_being_generated_queues_nothing_more()
    {
        Queue::fake();
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $review = WeeklyReview::factory()->for($owner)->pending()->create(['week' => '2026-09-21']);

        $response = $this->actingAs($owner)->post(route('reviews.store'), ['week' => '2026-09-21']);

        $response->assertRedirect(route('reviews.show', $review));
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidWeeks(): array
    {
        return [
            'missing' => [null],
            'not a date' => ['last week'],
            'not a real day' => ['2026-02-30'],
        ];
    }

    #[DataProvider('invalidWeeks')]
    public function test_asking_for_a_review_needs_a_day_of_the_week(mixed $week)
    {
        Queue::fake();
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('reviews.store'), ['week' => $week]);

        $response->assertSessionHasErrors('week');
        Queue::assertNothingPushed();
    }

    public function test_a_review_being_generated_for_the_first_time_has_no_content_yet()
    {
        $owner = User::factory()->create();
        $review = WeeklyReview::factory()->for($owner)->pending()->create(['week' => '2026-09-21']);

        $response = $this->actingAs($owner)->get(route('reviews.show', $review));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('reviews/show')
            ->where('review', [
                'id' => $review->id,
                'week' => '2026-09-21',
                'status' => 'pending',
                'summary' => null,
                'muscle_notes' => null,
                'advice' => null,
                'model' => null,
                'generated_at' => null,
                'rating' => null,
                'rating_comment' => null,
            ])
        );
    }

    public function test_a_failed_regeneration_shows_as_failed_with_the_earlier_content()
    {
        $this->travelTo('2026-09-29 06:00:00');
        $owner = User::factory()->create();
        $review = WeeklyReview::factory()->for($owner)->failed()->create([
            'week' => '2026-09-21',
            'summary' => 'Chest fell short.',
            'muscle_notes' => [['muscle' => 'chest', 'note' => 'Missed by 500 kg.']],
            'advice' => ['Add a fourth Set of bench.'],
            'model' => 'gpt-test',
        ]);

        $response = $this->actingAs($owner)->get(route('reviews.show', $review));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('review.status', 'failed')
            ->where('review.summary', 'Chest fell short.')
            ->where('review.muscle_notes', [['muscle' => 'chest', 'note' => 'Missed by 500 kg.']])
            ->where('review.advice', ['Add a fourth Set of bench.'])
            ->where('review.model', 'gpt-test')
            ->where('review.generated_at', '2026-09-29T06:00:00+00:00')
        );
    }

    public function test_another_users_review_returns_404()
    {
        $theirs = WeeklyReview::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('reviews.show', $theirs))->assertNotFound();
    }

    public function test_the_dashboard_shows_the_owners_latest_weekly_review()
    {
        $owner = User::factory()->create();
        WeeklyReview::factory()->for($owner)->create(['week' => '2026-09-14']);
        $latest = WeeklyReview::factory()->for($owner)->create(['week' => '2026-09-21', 'summary' => 'A solid Week.']);
        WeeklyReview::factory()->create(['week' => '2026-09-28']);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('latestReview.id', $latest->id)
            ->where('latestReview.week', '2026-09-21')
            ->where('latestReview.status', 'done')
            ->where('latestReview.summary', 'A solid Week.')
        );
    }

    public function test_the_dashboard_has_no_latest_weekly_review_before_the_first()
    {
        WeeklyReview::factory()->create();
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page->where('latestReview', null));
    }

    public function test_the_review_list_shows_the_owners_reviews_by_week_newest_first()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $older = WeeklyReview::factory()->for($owner)->create(['week' => '2026-09-07', 'summary' => 'An older Week.']);
        $newest = WeeklyReview::factory()->for($owner)->pending()->create(['week' => '2026-09-21']);
        $middle = WeeklyReview::factory()->for($owner)->failed()->create(['week' => '2026-09-14', 'summary' => 'Kept from before.']);
        WeeklyReview::factory()->create(['week' => '2026-09-28']);

        $response = $this->actingAs($owner)->get(route('reviews.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('reviews/index')
            ->where('currentWeek', '2026-09-28')
            ->where('reviews', [
                ['id' => $newest->id, 'week' => '2026-09-21', 'status' => 'pending', 'summary' => null],
                ['id' => $middle->id, 'week' => '2026-09-14', 'status' => 'failed', 'summary' => 'Kept from before.'],
                ['id' => $older->id, 'week' => '2026-09-07', 'status' => 'done', 'summary' => 'An older Week.'],
            ])
        );
    }

    public function test_a_past_review_opens_with_its_content()
    {
        $owner = User::factory()->create();
        $review = WeeklyReview::factory()->for($owner)->create([
            'week' => '2026-08-31',
            'summary' => 'Back after a week off.',
            'muscle_notes' => [['muscle' => 'lats', 'note' => 'Met.']],
            'advice' => ['Add weight to rows.'],
        ]);

        $response = $this->actingAs($owner)->get(route('reviews.show', $review));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('reviews/show')
            ->where('review.id', $review->id)
            ->where('review.week', '2026-08-31')
            ->where('review.status', 'done')
            ->where('review.summary', 'Back after a week off.')
            ->where('review.muscle_notes', [['muscle' => 'lats', 'note' => 'Met.']])
            ->where('review.advice', ['Add weight to rows.'])
        );
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
        $this->post(route('reviews.store'), ['week' => '2026-09-21'])->assertRedirect(route('login'));
        $this->get(route('reviews.show', WeeklyReview::factory()->create()))->assertRedirect(route('login'));
    }
}
