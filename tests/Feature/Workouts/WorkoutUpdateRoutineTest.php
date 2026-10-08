<?php

namespace Tests\Feature\Workouts;

use App\Enums\SetKind;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WorkoutUpdateRoutineTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_updating_the_routine_replaces_its_exercises_order_and_sets_with_the_workouts()
    {
        $routine = Routine::factory()->withExercises(2, 3)->create(['name' => 'Push day']);
        $workout = Workout::factory()->for($routine->user)->finished()->create(['routine_id' => $routine->id]);
        $press = $this->performNewExercise($workout, [
            WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => '40.00'])->done(),
            WorkoutSet::factory()->state(['target_reps' => 6, 'target_weight' => '42.50'])->missed(),
        ]);
        $squat = $this->performNewExercise($workout, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '100.00'])->warmUp()->done(),
        ]);

        $response = $this->actingAs($routine->user)->put(route('workouts.routine.update', $workout));

        $response
            ->assertRedirect(route('workouts.show', $workout))
            ->assertInertiaFlash('toast.message', 'Routine updated from this Workout.');

        $this->assertSame('Push day', $routine->refresh()->name);
        $this->assertSame(
            [
                [$press->id, [[8, '40.00', SetKind::Working], [6, '42.50', SetKind::Working]]],
                [$squat->id, [[5, '100.00', SetKind::WarmUp]]],
            ],
            $this->plan($routine),
        );
        $this->assertDatabaseCount('routine_exercises', 2);
        $this->assertDatabaseCount('routine_sets', 3);
    }

    public function test_a_set_without_a_target_takes_its_actual()
    {
        $workout = $this->workoutFromRoutine();
        $curl = $this->performNewExercise($workout, [
            WorkoutSet::factory()->withoutTarget()->state(['actual_reps' => 12, 'actual_weight' => '15.00']),
            WorkoutSet::factory()->withoutTarget()->warmUp()->state(['actual_reps' => 15, 'actual_weight' => '7.50']),
        ]);

        $this->actingAs($workout->user)->put(route('workouts.routine.update', $workout));

        $this->assertSame([[$curl->id, [[12, '15.00', SetKind::Working], [15, '7.50', SetKind::WarmUp]]]], $this->plan($workout->routine));
    }

    public function test_sets_with_neither_a_target_nor_an_actual_are_dropped_with_an_exercise_left_without_sets()
    {
        $workout = $this->workoutFromRoutine();
        $rowing = $this->performNewExercise($workout, [
            WorkoutSet::factory()->withoutTarget()->notDone(),
            WorkoutSet::factory()->state(['target_reps' => 10, 'target_weight' => '60.00'])->notDone(),
            WorkoutSet::factory()->withoutTarget()->notDone(),
        ]);
        $this->performNewExercise($workout, [
            WorkoutSet::factory()->withoutTarget()->notDone(),
        ]);

        $this->actingAs($workout->user)->put(route('workouts.routine.update', $workout));

        $this->assertSame([[$rowing->id, [[10, '60.00', SetKind::Working]]]], $this->plan($workout->routine));
    }

    public function test_the_workout_page_names_the_routine_to_update_only_when_it_has_one()
    {
        $workout = $this->workoutFromRoutine();
        $workout->routine->update(['name' => 'Push day']);
        $empty = Workout::factory()->for($workout->user)->finished()->create(['started_at' => now()->subWeek()]);

        $this->actingAs($workout->user)
            ->get(route('workouts.show', $workout))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workout.routine', ['id' => $workout->routine_id, 'name' => 'Push day', 'archived' => false])
                ->etc());
        $this->get(route('workouts.show', $empty))
            ->assertInertia(fn (Assert $page) => $page->where('workout.routine', null)->etc());
    }

    public function test_a_workout_started_empty_has_no_routine_to_update()
    {
        $workout = Workout::factory()->withExercises(1, 2)->create();
        Routine::factory()->for($workout->user)->withExercises(1, 2)->create();

        $response = $this->actingAs($workout->user)->put(route('workouts.routine.update', $workout));

        $response->assertForbidden();

        $this->assertDatabaseCount('routine_exercises', 1);
        $this->assertDatabaseCount('routine_sets', 2);
    }

    public function test_other_workouts_started_from_the_routine_are_unaffected()
    {
        $workout = $this->workoutFromRoutine();
        $earlier = Workout::factory()->for($workout->user)->withExercises(2, 2)->finished()->create([
            'routine_id' => $workout->routine_id,
            'started_at' => now()->subWeek(),
        ]);
        $before = $earlier->sets()->orderBy('workout_sets.id')->get(['workout_sets.*'])->toArray();
        $this->performNewExercise($workout, [WorkoutSet::factory()->done()]);

        $this->actingAs($workout->user)->put(route('workouts.routine.update', $workout));

        $this->assertSame($before, $earlier->sets()->orderBy('workout_sets.id')->get(['workout_sets.*'])->toArray());
        $this->assertSame([0, 1], $earlier->exercises()->pluck('position')->all());
    }

    public function test_logging_and_finishing_a_workout_leave_its_routine_unchanged()
    {
        $workout = $this->workoutFromRoutine();
        $planBefore = $this->plan($workout->routine);
        $this->performNewExercise($workout, [WorkoutSet::factory()->withoutTarget()]);
        $set = $workout->sets()->sole();

        $this->actingAs($workout->user)->put(route('workouts.sets.done', [$workout, $set]), ['actual_reps' => 8, 'actual_weight' => 50]);
        $this->post(route('workouts.finish', $workout));

        $this->assertSame($planBefore, $this->plan($workout->routine));
    }

    /**
     * An in-progress Workout of the owner, started from a Routine that plans other Exercises.
     */
    private function workoutFromRoutine(): Workout
    {
        $routine = Routine::factory()->withExercises(2, 3)->create();

        return Workout::factory()->for($routine->user)->create(['routine_id' => $routine->id]);
    }

    /**
     * Add a new Exercise of the owner after the Workout's others, performed with the given Sets in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    private function performNewExercise(Workout $workout, array $sets): Exercise
    {
        $exercise = Exercise::factory()->for($workout->user)->create();

        $this->perform($workout, $exercise, $sets);

        return $exercise;
    }

    /**
     * The Routine's planned Exercises in order, each with its Sets as [Target reps, Target weight, kind].
     *
     * @return list<array{int, list<array{int, string, SetKind}>}>
     */
    private function plan(Routine $routine): array
    {
        return $routine->exercises()->with('sets')->get()
            ->map(fn (RoutineExercise $planned) => [
                $planned->exercise_id,
                $planned->sets->map(fn (RoutineSet $set) => [$set->target_reps, $set->target_weight, $set->kind])->all(),
            ])
            ->all();
    }
}
