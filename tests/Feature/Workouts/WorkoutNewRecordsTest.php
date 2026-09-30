<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WorkoutNewRecordsTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_the_first_set_of_an_exercise_is_not_flagged_and_a_heavier_one_names_the_records_it_beat()
    {
        $squat = Exercise::factory()->create();
        $workout = $this->workout($squat->user, '-1 hour');
        $this->perform($workout, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(5, '110.00')]);

        $response = $this->actingAs($squat->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.0.new_records', [])
            ->where('workout.exercises.0.sets.1.new_records', ['heaviest', 'e1rm', 'tonnage'])
            ->etc()
        );
    }

    public function test_a_set_is_flagged_against_the_sets_of_workouts_that_started_earlier()
    {
        $bench = Exercise::factory()->create();
        $this->perform($this->workout($bench->user, '-1 week'), $bench, [$this->doneSet(5, '100.00')]);
        $this->perform($this->workout($bench->user, '+1 day'), $bench, [$this->doneSet(10, '100.00')]);
        $today = $this->workout($bench->user, '-1 hour');
        $this->perform($today, $bench, [$this->doneSet(6, '100.00'), $this->doneSet(5, '100.00')]);

        $response = $this->actingAs($bench->user)->get(route('workouts.show', $today));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.0.new_records', ['e1rm', 'reps', 'tonnage'])
            ->where('workout.exercises.0.sets.1.new_records', [])
            ->etc()
        );
    }

    public function test_most_reps_at_a_weight_is_only_beaten_at_a_weight_done_before()
    {
        $row = Exercise::factory()->create();
        $workout = $this->workout($row->user, '-1 hour');
        $this->perform($workout, $row, [
            $this->doneSet(5, '100.00'),
            $this->doneSet(6, '80.00'),
            $this->doneSet(6, '100.00'),
        ]);

        $response = $this->actingAs($row->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.1.new_records', [])
            ->where('workout.exercises.0.sets.2.new_records', ['e1rm', 'reps', 'tonnage'])
            ->etc()
        );
    }

    public function test_a_bodyweight_exercise_beats_estimated_1rm_and_tonnage_with_a_heavier_bodyweight_at_the_same_added_load()
    {
        $pullUp = Exercise::factory()->bodyweight()->create();
        $this->perform($this->workout($pullUp->user, '-1 week', ['bodyweight' => '80.00']), $pullUp, [$this->doneSet(10, '0.00')]);
        $today = $this->workout($pullUp->user, '-1 hour', ['bodyweight' => '85.00']);
        $this->perform($today, $pullUp, [$this->doneSet(10, '0.00')]);

        $response = $this->actingAs($pullUp->user)->get(route('workouts.show', $today));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.0.new_records', ['e1rm', 'tonnage'])
            ->etc()
        );
    }

    public function test_warm_up_and_not_done_sets_are_never_flagged_and_never_beaten()
    {
        $deadlift = Exercise::factory()->create();
        $workout = $this->workout($deadlift->user, '-1 hour');
        $this->perform($workout, $deadlift, [
            $this->doneSet(5, '60.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            $this->doneSet(5, '140.00')->warmUp(),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '150.00'])->notDone(),
            $this->doneSet(5, '120.00'),
        ]);

        $response = $this->actingAs($deadlift->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.0.new_records', [])
            ->where('workout.exercises.0.sets.1.new_records', [])
            ->where('workout.exercises.0.sets.2.new_records', [])
            ->where('workout.exercises.0.sets.3.new_records', [])
            ->where('workout.exercises.0.sets.4.new_records', ['heaviest', 'e1rm', 'tonnage'])
            ->etc()
        );
    }

    public function test_correcting_an_earlier_set_moves_the_flag()
    {
        $squat = Exercise::factory()->create();
        $earlier = $this->workout($squat->user, '-1 week');
        $this->perform($earlier, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(5, '105.00')]);
        $today = $this->workout($squat->user, '-1 hour');
        $this->perform($today, $squat, [$this->doneSet(5, '110.00')]);
        $mistyped = $earlier->sets()->orderBy('position')->get()->last();

        $this->actingAs($squat->user)
            ->patch(route('workouts.sets.update', [$earlier, $mistyped]), ['actual_reps' => 5, 'actual_weight' => 115])
            ->assertSessionHasNoErrors();

        $this->get(route('workouts.show', $earlier))->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.1.new_records', ['heaviest', 'e1rm', 'tonnage'])
            ->etc()
        );
        $this->get(route('workouts.show', $today))->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.0.new_records', [])
            ->etc()
        );
    }

    public function test_deleting_an_earlier_set_moves_the_flag()
    {
        $squat = Exercise::factory()->create();
        $workout = $this->workout($squat->user, '-1 hour');
        $this->perform($workout, $squat, [
            $this->doneSet(5, '100.00'),
            $this->doneSet(5, '120.00'),
            $this->doneSet(5, '110.00'),
        ]);
        $heaviest = $workout->sets()->orderBy('position')->get()->get(1);

        $this->actingAs($squat->user)->delete(route('workouts.sets.destroy', [$workout, $heaviest]));

        $this->get(route('workouts.show', $workout))->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.sets.1.new_records', ['heaviest', 'e1rm', 'tonnage'])
            ->etc()
        );
    }

    /**
     * A Workout of the owner that started the given time ago.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function workout(User $owner, string $startedAgo, array $attributes = []): Workout
    {
        return Workout::factory()->for($owner)->create(['started_at' => now()->modify($startedAgo), ...$attributes]);
    }
}
