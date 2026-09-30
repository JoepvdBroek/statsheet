<?php

namespace Tests\Feature;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThisWeeksVolumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_done_set_counts_in_full_to_primary_muscles_and_half_to_secondary_muscles()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $benchPress, [$this->doneSet(10, '100.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.starts_on', '2026-09-28')
            ->where('thisWeek.muscles', [
                ['muscle' => 'chest', 'volume' => 1000, 'goal' => null, 'met' => null],
                ['muscle' => 'triceps', 'volume' => 500, 'goal' => null, 'met' => null],
            ])
        );
    }

    public function test_warm_up_and_not_done_sets_are_left_out()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $workout = Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 10:00:00']);
        $this->perform($workout, $squat, [
            $this->doneSet(10, '60.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '100.00'])->notDone(),
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'quadriceps', 'volume' => 500, 'goal' => null, 'met' => null]])
        );
    }

    public function test_a_muscle_whose_done_sets_add_up_to_no_volume_is_left_out()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $plankReach = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Abdominals)->create();
        $workout = Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 10:00:00']);
        $this->perform($workout, $plankReach, [$this->doneSet(12, '0.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles', []));
    }

    public function test_a_bodyweight_exercise_adds_the_workouts_bodyweight_to_the_added_load()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['bodyweight' => '90.00']);
        $pullUp = Exercise::factory()->for($owner)->bodyweight()->primaryMuscle(Muscle::Lats)->withSecondaryMuscle(Muscle::Biceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-29 10:00:00', 'bodyweight' => '80.00']);
        $this->perform($workout, $pullUp, [$this->doneSet(10, '0.00'), $this->doneSet(5, '10.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'lats', 'volume' => 1250, 'goal' => null, 'met' => null],
                ['muscle' => 'biceps', 'volume' => 625, 'goal' => null, 'met' => null],
            ])
        );
    }

    public function test_a_bodyweight_exercise_counts_only_its_added_load_when_the_workout_has_no_bodyweight()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['bodyweight' => null]);
        $dip = Exercise::factory()->for($owner)->bodyweight()->primaryMuscle(Muscle::Triceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-29 10:00:00', 'bodyweight' => null]);
        $this->perform($workout, $dip, [$this->doneSet(10, '0.00'), $this->doneSet(8, '12.50')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'triceps', 'volume' => 100, 'goal' => null, 'met' => null]])
        );
    }

    public function test_a_set_marked_done_in_the_workout_in_progress_counts_straight_away()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $row = Exercise::factory()->for($owner)->primaryMuscle(Muscle::MiddleBack)->create();
        $workout = Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 11:00:00']);
        $this->perform($workout, $row, [WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => '70.00'])->notDone()]);
        $this->actingAs($owner)->put(route('workouts.sets.done', [$workout, $workout->sets()->sole()]));

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'middle back', 'volume' => 560, 'goal' => null, 'met' => null]])
        );
    }

    public function test_correcting_a_set_in_history_shows_up_straight_away()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $squat, [$this->doneSet(5, '100.00')]);
        $this->actingAs($owner)->patch(route('workouts.sets.update', [$workout, $workout->sets()->sole()]), ['actual_reps' => 3, 'actual_weight' => '100']);

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'quadriceps', 'volume' => 300, 'goal' => null, 'met' => null]])
        );
    }

    public function test_a_deleted_workout_no_longer_counts()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $squat, [$this->doneSet(5, '100.00')]);
        $this->actingAs($owner)->delete(route('workouts.destroy', $workout));

        $response = $this->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles', []));
    }

    public function test_on_sunday_night_this_week_includes_a_workout_started_at_23_30_local_that_is_monday_in_utc()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 23:45:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 03:30:00']), $squat, [$this->doneSet(1, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => '2026-10-05 03:30:00']), $squat, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.starts_on', '2026-09-28')
            ->where('thisWeek.muscles', [['muscle' => 'quadriceps', 'volume' => 500, 'goal' => null, 'met' => null]])
        );
    }

    public function test_on_monday_morning_a_new_week_starts_at_local_midnight()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:15:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-10-05 03:30:00']), $squat, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => '2026-10-05 04:05:00']), $squat, [$this->doneSet(2, '100.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.starts_on', '2026-10-05')
            ->where('thisWeek.muscles', [['muscle' => 'quadriceps', 'volume' => 200, 'goal' => null, 'met' => null]])
        );
    }

    public function test_another_users_workouts_do_not_count()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $someoneElses = Workout::factory()->create(['started_at' => '2026-09-29 10:00:00']);
        $this->perform($someoneElses, Exercise::factory()->for($someoneElses->user)->primaryMuscle(Muscle::Quadriceps)->create(), [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles', []));
    }

    /**
     * Add an Exercise after the Workout's others, performed with the given Sets in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    private function perform(Workout $workout, Exercise $exercise, array $sets): void
    {
        $performed = WorkoutExercise::factory()
            ->for($workout)
            ->for($exercise)
            ->create(['position' => $workout->exercises()->count()]);

        foreach ($sets as $position => $set) {
            $set->for($performed)->create(['position' => $position]);
        }
    }

    private function doneSet(int $reps, string $weight): WorkoutSetFactory
    {
        return WorkoutSet::factory()->state(['target_reps' => $reps, 'target_weight' => $weight])->done();
    }
}
