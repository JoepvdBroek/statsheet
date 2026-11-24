<?php

namespace Tests\Feature;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\Goal;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class SetCountTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_a_done_set_counts_as_one_for_each_primary_muscle_and_half_for_each_secondary_muscle_whatever_its_reps()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $closeGripBenchPress = Exercise::factory()->for($owner)
            ->primaryMuscle(Muscle::Chest)
            ->primaryMuscle(Muscle::Triceps)
            ->withSecondaryMuscle(Muscle::Shoulders)
            ->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $closeGripBenchPress, [
            $this->doneSet(10, '100.00'),
            $this->doneSet(1, '120.00'),
            $this->doneSet(6, '100.00'),
        ]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'chest', 'volume' => 1720, 'set_count' => 3, 'direct_sets' => 3, 'goal' => null, 'met' => null],
                ['muscle' => 'triceps', 'volume' => 1720, 'set_count' => 3, 'direct_sets' => 3, 'goal' => null, 'met' => null],
                ['muscle' => 'shoulders', 'volume' => 860, 'set_count' => 1.5, 'direct_sets' => 0, 'goal' => null, 'met' => null],
            ])
        );
    }

    public function test_direct_sets_are_the_part_from_exercises_where_the_muscle_is_primary()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $pushdown = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Triceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $benchPress, [$this->doneSet(10, '100.00'), $this->doneSet(10, '100.00'), $this->doneSet(10, '100.00')]);
        $this->perform($workout, $pushdown, [$this->doneSet(10, '20.00'), $this->doneSet(10, '20.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'chest', 'volume' => 3000, 'set_count' => 3, 'direct_sets' => 3, 'goal' => null, 'met' => null],
                ['muscle' => 'triceps', 'volume' => 1900, 'set_count' => 3.5, 'direct_sets' => 2, 'goal' => null, 'met' => null],
            ])
        );
    }

    public function test_only_done_working_sets_count_while_drop_sets_add_volume_alone()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $curl = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Biceps)->create();
        $calfRaise = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Calves)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $squat, [
            $this->doneSet(10, '60.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            $this->doneSet(5, '100.00'),
            $this->doneSet(5, '80.00')->drop(),
            WorkoutSet::factory()->notDone(),
        ]);
        $this->perform($workout, $curl, [$this->doneSet(10, '10.00')->drop()]);
        $this->perform($workout, $calfRaise, [$this->doneSet(10, '50.00')->warmUp(), WorkoutSet::factory()->notDone()]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'quadriceps', 'volume' => 1400, 'set_count' => 2, 'direct_sets' => 2, 'goal' => null, 'met' => null],
                ['muscle' => 'biceps', 'volume' => 100, 'set_count' => 0, 'direct_sets' => 0, 'goal' => null, 'met' => null],
            ])
        );
    }

    public function test_muscles_with_only_sets_at_0_kg_or_only_a_goal_follow_those_with_volume_by_name()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '800.00', 'effective_week' => '2026-09-28']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Neck, 'weekly_minimum' => '200.00', 'effective_week' => '2026-09-28']);
        $plankReach = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Abdominals)->create();
        $neckCurl = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Neck)->create();
        $pushdown = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Triceps)->create();
        $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $this->perform($workout, $neckCurl, [$this->doneSet(15, '0.00'), $this->doneSet(15, '0.00')]);
        $this->perform($workout, $plankReach, [$this->doneSet(12, '0.00')]);
        $this->perform($workout, $pushdown, [$this->doneSet(10, '20.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'triceps', 'volume' => 200, 'set_count' => 1, 'direct_sets' => 1, 'goal' => null, 'met' => null],
                ['muscle' => 'abdominals', 'volume' => 0, 'set_count' => 1, 'direct_sets' => 1, 'goal' => null, 'met' => null],
                ['muscle' => 'chest', 'volume' => 0, 'set_count' => 0, 'direct_sets' => 0, 'goal' => 800, 'met' => false],
                ['muscle' => 'neck', 'volume' => 0, 'set_count' => 2, 'direct_sets' => 2, 'goal' => 200, 'met' => false],
            ])
        );
    }

    public function test_sets_count_in_the_week_their_workout_started_in_the_owners_timezone()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 23:45:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $sundayNightBefore = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 03:30:00']);
        $mondayJustAfterMidnight = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 04:05:00']);
        $sundayNight = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-10-05 03:30:00']);
        $this->perform($sundayNightBefore, $squat, [$this->doneSet(5, '100.00')]);
        $this->perform($mondayJustAfterMidnight, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(5, '100.00')]);
        $this->perform($sundayNight, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(5, '100.00'), $this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.starts_on', '2026-09-28')
            ->where('thisWeek.muscles.0.set_count', 5)
        );
    }

    public function test_the_workout_in_progress_counts_with_the_finished_ones()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $finished = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-28 10:00:00']);
        $inProgress = Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 11:00:00']);
        $this->perform($finished, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(5, '100.00')]);
        $this->perform($inProgress, $squat, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles.0.set_count', 3));
    }

    public function test_another_users_sets_do_not_add_to_the_owners_set_count()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $workout = Workout::factory()->for($owner)->create(['started_at' => '2026-09-29 10:00:00']);
        $this->perform($workout, Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create(), [$this->doneSet(5, '100.00')]);
        $someoneElses = Workout::factory()->create(['started_at' => '2026-09-29 10:00:00']);
        $this->perform($someoneElses, Exercise::factory()->for($someoneElses->user)->primaryMuscle(Muscle::Quadriceps)->create(), [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles.0.set_count', 1));
    }
}
