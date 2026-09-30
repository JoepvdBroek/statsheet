<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\ReviewStatus;
use App\Jobs\GenerateWeeklyReview;
use App\Models\User;
use App\Models\WeeklyReview;
use App\Models\Workout;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleWeeklyReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_the_review_of_the_week_just_ended_at_monday_six_in_the_morning()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'UTC']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-28 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $review = WeeklyReview::sole();
        $this->assertTrue($review->user->is($owner));
        $this->assertSame('2026-09-21', $review->week->toDateString());
        $this->assertSame(ReviewStatus::Pending, $review->status);
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job) => $job->review->is($review));
    }

    public function test_skips_a_week_without_workouts()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'UTC']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-20 18:00:00']);
        Workout::factory()->for($owner)->create(['started_at' => '2026-09-28 05:30:00']);
        $this->travelTo('2026-09-28 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $this->assertDatabaseEmpty('weekly_reviews');
        Queue::assertNothingPushed();
    }

    public function test_waits_for_six_in_the_morning_in_the_owners_timezone()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-28 03:59:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_queues_only_for_owners_whose_monday_six_has_come()
    {
        Queue::fake();
        $amsterdam = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $newYork = User::factory()->create(['timezone' => 'America/New_York']);
        Workout::factory()->for($amsterdam)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        Workout::factory()->for($newYork)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-28 04:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $this->assertTrue(WeeklyReview::sole()->user->is($amsterdam));
        Queue::assertPushed(GenerateWeeklyReview::class, 1);
    }

    public function test_queues_nothing_at_six_on_another_day()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'UTC']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-29 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_counts_the_workouts_of_the_week_just_ended_in_the_owners_timezone()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-20 22:30:00']);
        $this->travelTo('2026-09-28 04:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $this->assertSame('2026-09-21', WeeklyReview::sole()->week->toDateString());
        Queue::assertPushed(GenerateWeeklyReview::class, 1);
    }

    public function test_queues_once_when_run_again_in_the_same_hour()
    {
        Queue::fake();
        $owner = User::factory()->create(['timezone' => 'UTC']);
        Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-23 18:00:00']);

        $this->travelTo('2026-09-28 06:00:00');
        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();
        $this->travelTo('2026-09-28 06:30:00');
        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $this->assertSame(1, WeeklyReview::count());
        Queue::assertPushed(GenerateWeeklyReview::class, 1);
    }

    public function test_leaves_a_review_written_after_the_week_ended_alone()
    {
        Queue::fake();
        $review = WeeklyReview::factory()->create([
            'week' => '2026-09-21',
            'status' => ReviewStatus::Done,
            'summary' => 'Asked for early on Monday.',
            'generated_at' => '2026-09-28 05:30:00',
        ]);
        $review->user->update(['timezone' => 'UTC']);
        Workout::factory()->for($review->user)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-28 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $review->refresh();
        $this->assertSame(ReviewStatus::Done, $review->status);
        $this->assertSame('Asked for early on Monday.', $review->summary);
        Queue::assertNothingPushed();
    }

    public function test_queues_a_fresh_review_of_a_week_reviewed_before_it_ended()
    {
        Queue::fake();
        $review = WeeklyReview::factory()->create([
            'week' => '2026-09-21',
            'status' => ReviewStatus::Done,
            'summary' => 'Asked for on Thursday.',
            'generated_at' => '2026-09-24 20:00:00',
        ]);
        $review->user->update(['timezone' => 'UTC']);
        Workout::factory()->for($review->user)->finished()->create(['started_at' => '2026-09-26 10:00:00']);
        $this->travelTo('2026-09-28 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $review->refresh();
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertSame('Asked for on Thursday.', $review->summary);
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job) => $job->review->is($review));
    }

    public function test_queues_the_review_again_after_a_failed_one()
    {
        Queue::fake();
        $review = WeeklyReview::factory()->failed()->create(['week' => '2026-09-21']);
        $review->user->update(['timezone' => 'UTC']);
        Workout::factory()->for($review->user)->finished()->create(['started_at' => '2026-09-23 18:00:00']);
        $this->travelTo('2026-09-28 06:00:00');

        $this->artisan('app:schedule-weekly-reviews')->assertSuccessful();

        $this->assertSame(ReviewStatus::Pending, $review->refresh()->status);
        Queue::assertPushed(GenerateWeeklyReview::class, fn (GenerateWeeklyReview $job) => $job->review->is($review));
    }

    public function test_the_scheduler_runs_it_every_hour()
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'app:schedule-weekly-reviews'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
