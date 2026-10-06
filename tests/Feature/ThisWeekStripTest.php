<?php

namespace Tests\Feature;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class ThisWeekStripTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_the_workout_in_progress_and_its_done_sets_count_this_week()
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']), $squat, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 15:30:00']), $squat, [
            $this->doneSet(2, '100.00'),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '100.00'])->notDone(),
        ]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.workouts', 2)
            ->where('thisWeek.volume', 700)
        );
    }

    public function test_the_volume_is_the_sum_over_muscles_so_a_secondary_muscle_adds_half()
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->withSecondaryMuscle(Muscle::Glutes)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']), $squat, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.volume', 750));
    }

    public function test_last_weeks_volume_counts_only_workouts_started_up_to_the_same_weekday_and_time()
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-21 00:00:00', 'Europe/Amsterdam')->utc()]), $squat, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-23 17:30:00', 'Europe/Amsterdam')->utc()]), $squat, [$this->doneSet(3, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-23 18:30:00', 'Europe/Amsterdam')->utc()]), $squat, [$this->doneSet(8, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-20 23:30:00', 'Europe/Amsterdam')->utc()]), $squat, [$this->doneSet(9, '100.00')]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.workouts', 0)
            ->where('thisWeek.volume', 0)
            ->where('thisWeek.last_week_volume', 800)
        );
    }

    public function test_on_sunday_night_a_workout_started_at_23_30_local_that_is_monday_in_utc_counts_in_its_local_week()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 23:45:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => '2026-10-05 03:30:00']), $squat, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 03:30:00']), $squat, [$this->doneSet(2, '100.00')]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.workouts', 1)
            ->where('thisWeek.volume', 500)
            ->where('thisWeek.last_week_volume', 200)
        );
    }

    public function test_on_monday_morning_a_new_week_starts_at_local_midnight_and_last_week_counts_up_to_the_same_moment()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:15:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => CarbonImmutable::parse('2026-10-05 00:05:00', 'America/New_York')->utc()]), $squat, [$this->doneSet(1, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-10-04 23:30:00', 'America/New_York')->utc()]), $squat, [$this->doneSet(9, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-28 00:10:00', 'America/New_York')->utc()]), $squat, [$this->doneSet(3, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => CarbonImmutable::parse('2026-09-28 00:20:00', 'America/New_York')->utc()]), $squat, [$this->doneSet(7, '100.00')]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.workouts', 1)
            ->where('thisWeek.volume', 100)
            ->where('thisWeek.last_week_volume', 300)
        );
    }

    public function test_another_users_workouts_do_not_count()
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $someoneElse = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $squat = Exercise::factory()->for($someoneElse)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($someoneElse)->create(['started_at' => '2026-09-30 10:00:00']), $squat, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($someoneElse)->finished()->create(['started_at' => '2026-09-22 10:00:00']), $squat, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.workouts', 0)
            ->where('thisWeek.volume', 0)
            ->where('thisWeek.last_week_volume', 0)
        );
    }
}
