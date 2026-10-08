<?php

namespace Tests\Feature\Workouts;

use App\Enums\SetKind;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WorkoutSwapExerciseTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_swapping_puts_the_new_exercise_in_the_same_place_with_the_same_set_kinds_and_no_targets_when_never_done()
    {
        $workout = Workout::factory()->withExercises(3, 1)->create();
        [$first, $swapped, $third] = $workout->exercises;
        $this->replaceSets($swapped, [
            WorkoutSet::factory()->warmUp(),
            WorkoutSet::factory(),
            WorkoutSet::factory()->drop(),
        ]);
        $dumbbellFly = Exercise::factory()->for($workout->user)->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.swap', [$workout, $swapped]), ['exercise_id' => $dumbbellFly->id]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $workout));

        $this->assertSame(
            [$first->exercise_id, $dumbbellFly->id, $third->exercise_id],
            $workout->exercises()->pluck('exercise_id')->all(),
        );
        $this->assertSame(
            [[null, null, SetKind::WarmUp], [null, null, SetKind::Working], [null, null, SetKind::Drop]],
            $this->sets($workout->exercises()->get()->get(1)),
        );
    }

    public function test_the_new_exercise_pre_fills_from_its_most_recent_earlier_workout_and_falls_back_to_the_swapped_out_kind()
    {
        $owner = User::factory()->create();
        $dumbbellFly = Exercise::factory()->for($owner)->create();
        $this->perform($this->finishedWorkout($owner, '-2 days'), $dumbbellFly, [
            $this->doneSet(10, '20.00')->warmUp(),
            WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => '30.00'])->missed(),
        ]);
        $workout = $this->workoutInProgress($owner);
        $swapped = $this->performedWith($workout, [WorkoutSet::factory(), WorkoutSet::factory(), WorkoutSet::factory()->drop()]);

        $this->actingAs($owner)->post(route('workouts.exercises.swap', [$workout, $swapped]), ['exercise_id' => $dumbbellFly->id]);

        $this->assertSame(
            [[10, '20.00', SetKind::WarmUp], [8, '30.00', SetKind::Working], [null, null, SetKind::Drop]],
            $this->sets($swapped->refresh()),
        );
    }

    public function test_pre_fill_skips_this_workout_and_later_ones()
    {
        $owner = User::factory()->create();
        $dumbbellFly = Exercise::factory()->for($owner)->create();
        $this->perform($this->finishedWorkout($owner, '-14 days'), $dumbbellFly, [$this->doneSet(8, '24.00')]);
        $this->perform($this->finishedWorkout($owner, '-2 days'), $dumbbellFly, [$this->doneSet(8, '28.00')]);
        $lastWeek = $this->finishedWorkout($owner, '-7 days');
        $this->perform($lastWeek, $dumbbellFly, [$this->doneSet(8, '26.00')]);
        $swapped = $this->performedWith($lastWeek, [WorkoutSet::factory()]);

        $this->actingAs($owner)->post(route('workouts.exercises.swap', [$lastWeek, $swapped]), ['exercise_id' => $dumbbellFly->id]);

        $this->assertSame([[8, '24.00', SetKind::Working]], $this->sets($swapped->refresh()));
    }

    public function test_an_exercise_with_a_done_set_cannot_be_swapped()
    {
        $workout = Workout::factory()->create();
        $swapped = $this->performedWith($workout, [$this->doneSet(12, '15.00'), WorkoutSet::factory()->state(['target_reps' => 12, 'target_weight' => '17.50'])]);
        $dumbbellFly = Exercise::factory()->for($workout->user)->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.swap', [$workout, $swapped]), ['exercise_id' => $dumbbellFly->id]);

        $response->assertSessionHasErrors(['exercise_id' => 'Swap an Exercise only before any of its Sets is done.']);
        $this->assertNotSame($dumbbellFly->id, $swapped->refresh()->exercise_id);
        $this->assertSame([[12, '15.00', SetKind::Working], [12, '17.50', SetKind::Working]], $this->sets($swapped));
    }

    public function test_another_users_exercise_cannot_be_swapped_in()
    {
        $workout = Workout::factory()->withExercises(1, 2)->create();
        $swapped = $workout->exercises[0];
        $someoneElses = Exercise::factory()->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.swap', [$workout, $swapped]), ['exercise_id' => $someoneElses->id]);

        $response->assertSessionHasErrors(['exercise_id' => 'Choose one of your Exercises in use.']);
        $this->assertNotSame($someoneElses->id, $swapped->refresh()->exercise_id);
        $this->assertSame(2, $swapped->sets()->count());
    }

    /**
     * A finished Workout of the owner that started the given time ago.
     */
    private function finishedWorkout(User $owner, string $startedAgo): Workout
    {
        return Workout::factory()->for($owner)->finished()->create(['started_at' => now()->modify($startedAgo)]);
    }

    private function workoutInProgress(User $owner): Workout
    {
        return Workout::factory()->for($owner)->create(['started_at' => now()]);
    }

    /**
     * An Exercise of the owner after the Workout's others, with the given Sets in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    private function performedWith(Workout $workout, array $sets): WorkoutExercise
    {
        $this->perform($workout, Exercise::factory()->for($workout->user)->create(), $sets);

        return $workout->exercises()->get()->last();
    }

    /**
     * Replace the Exercise's Sets with the given ones in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    private function replaceSets(WorkoutExercise $performed, array $sets): void
    {
        $performed->sets()->delete();

        foreach ($sets as $position => $set) {
            $set->for($performed)->create(['position' => $position]);
        }
    }

    /**
     * The Exercise's Sets in order as [Target reps, Target weight, kind].
     *
     * @return list<array{int|null, string|null, SetKind}>
     */
    private function sets(WorkoutExercise $performed): array
    {
        return $performed->sets()->get()
            ->map(fn (WorkoutSet $set) => [$set->target_reps, $set->target_weight, $set->kind])
            ->all();
    }
}
