<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkoutStartFromRoutineTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_from_a_routine_copies_its_exercises_in_order_with_its_targets_when_there_is_no_history()
    {
        $this->freezeSecond();
        $owner = User::factory()->create(['bodyweight' => '82.40']);
        $routine = Routine::factory()->for($owner)->create();
        $squat = $this->plan($routine, [[5, '100.00', true], [5, '140.00', false]]);
        $press = $this->plan($routine, [[8, '40.00', false], [8, '40.00', false], [6, '42.50', false]]);

        $response = $this->actingAs($owner)->post(route('routines.start', $routine));

        $workout = $owner->workouts()->sole();

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertTrue($workout->routine->is($routine));
        $this->assertTrue($workout->started_at->equalTo(now()));
        $this->assertSame('82.40', $workout->bodyweight);
        $this->assertSame(
            [
                [$squat->id, [[5, '100.00', true], [5, '140.00', false]]],
                [$press->id, [[8, '40.00', false], [8, '40.00', false], [6, '42.50', false]]],
            ],
            $this->plannedSets($workout),
        );
    }

    /**
     * The same Set last time as [Target reps, Target weight, Actual reps, Actual weight], and the Target it Pre-fills.
     * The Routine plans 3 × 100 kg.
     *
     * @return array<string, array{0: array{int|null, string|null, int|null, string|null}, 1: array{int, string}}>
     */
    public static function lastTimeSets(): array
    {
        return [
            'met target: its Actual' => [[5, '140.00', 6, '142.50'], [6, '142.50']],
            'no Target, done: its Actual' => [[null, null, 8, '60.00'], [8, '60.00']],
            'missed reps: its Target' => [[5, '140.00', 4, '145.00'], [5, '140.00']],
            'missed weight: its Target' => [[5, '140.00', 6, '137.50'], [5, '140.00']],
            'not done: its Target' => [[5, '140.00', null, null], [5, '140.00']],
            'no Target or Actual: the Routine\'s Target' => [[null, null, null, null], [3, '100.00']],
        ];
    }

    /**
     * @param  array{int|null, string|null, int|null, string|null}  $lastTime
     * @param  array{int, string}  $expectedTarget
     */
    #[DataProvider('lastTimeSets')]
    public function test_a_set_pre_fills_from_the_same_set_last_time(array $lastTime, array $expectedTarget)
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false]]);
        $this->perform($this->finishedWorkout($routine->user, '-2 days'), $squat, [
            WorkoutSet::factory()->state([
                'target_reps' => $lastTime[0],
                'target_weight' => $lastTime[1],
                'actual_reps' => $lastTime[2],
                'actual_weight' => $lastTime[3],
            ]),
        ]);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[...$expectedTarget, false]]]], $this->plannedSets($this->workoutInProgress($routine->user)));
    }

    public function test_a_set_with_no_set_at_its_position_last_time_pre_fills_with_the_routines_target()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false], [3, '110.00', false]]);
        $this->perform($this->finishedWorkout($routine->user, '-2 days'), $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '140.00'])->done(),
        ]);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '140.00', false], [3, '110.00', false]]]], $this->plannedSets($this->workoutInProgress($routine->user)));
    }

    public function test_set_n_is_the_nth_set_last_time_even_after_earlier_sets_were_removed()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false], [3, '110.00', false]]);
        $owner = $routine->user;
        $this->perform($this->finishedWorkout($owner, '-2 days'), $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '140.00'])->done(),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '145.00'])->done(),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '150.00'])->done(),
        ]);
        $this->actingAs($owner)->delete(route('workouts.sets.destroy', [
            $owner->workouts()->sole(),
            WorkoutSet::query()->where('target_weight', '140.00')->sole(),
        ]));

        $this->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '145.00', false], [5, '150.00', false]]]], $this->plannedSets($this->workoutInProgress($owner)));
    }

    public function test_the_routine_decides_the_set_count_even_when_last_time_had_more_sets()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false]]);
        $this->perform($this->finishedWorkout($routine->user, '-2 days'), $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '140.00'])->done(),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '150.00'])->done(),
        ]);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '140.00', false]]]], $this->plannedSets($this->workoutInProgress($routine->user)));
    }

    public function test_the_warm_up_flag_comes_from_the_set_that_supplied_the_target()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false], [3, '110.00', true]]);
        $this->perform($this->finishedWorkout($routine->user, '-2 days'), $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '60.00'])->done()->warmUp(),
            WorkoutSet::factory()->withoutTarget()->notDone(),
        ]);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '60.00', true], [3, '110.00', true]]]], $this->plannedSets($this->workoutInProgress($routine->user)));
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function mostRecentWorkoutSources(): array
    {
        return [
            'from a different Routine' => [true],
            'ad hoc' => [false],
        ];
    }

    #[DataProvider('mostRecentWorkoutSources')]
    public function test_pre_fill_uses_the_most_recent_workout_containing_the_exercise_whichever_routine_it_came_from(bool $fromAnotherRoutine)
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false]]);
        $owner = $routine->user;
        $this->perform($this->finishedWorkout($owner, '-9 days', $routine), $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '140.00'])->done(),
        ]);
        $mostRecent = $this->finishedWorkout($owner, '-2 days', $fromAnotherRoutine ? Routine::factory()->for($owner)->create() : null);
        $this->perform($mostRecent, $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '145.00'])->done(),
        ]);
        $this->perform($this->finishedWorkout($owner, '-1 day'), Exercise::factory()->for($owner)->create(), [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '150.00'])->done(),
        ]);

        $this->actingAs($owner)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '145.00', false]]]], $this->plannedSets($this->workoutInProgress($owner)));
    }

    public function test_pre_fill_uses_the_first_occurrence_of_the_exercise_in_the_most_recent_workout()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false]]);
        $mostRecent = $this->finishedWorkout($routine->user, '-2 days');
        $this->perform($mostRecent, $squat, [
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '140.00'])->done(),
        ]);
        $this->perform($mostRecent, $squat, [
            WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => '100.00'])->done(),
        ]);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame([[$squat->id, [[5, '140.00', false]]]], $this->plannedSets($this->workoutInProgress($routine->user)));
    }

    public function test_an_exercise_planned_twice_does_not_pre_fill_from_the_workout_being_started()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[3, '100.00', false]]);
        $this->plan($routine, [[8, '60.00', false]], $squat);

        $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $this->assertSame(
            [[$squat->id, [[3, '100.00', false]]], [$squat->id, [[8, '60.00', false]]]],
            $this->plannedSets($this->workoutInProgress($routine->user)),
        );
    }

    public function test_editing_or_archiving_the_routine_afterwards_leaves_the_workout_unchanged()
    {
        $routine = Routine::factory()->create();
        $squat = $this->plan($routine, [[5, '140.00', false], [5, '140.00', false]]);
        $press = Exercise::factory()->for($routine->user)->create();
        $this->actingAs($routine->user)->post(route('routines.start', $routine));
        $workout = $this->workoutInProgress($routine->user);

        $this->put(route('routines.update', $routine), [
            'name' => 'Push day B',
            'exercises' => [['exercise_id' => $press->id, 'sets' => [
                ['target_reps' => 8, 'target_weight' => 40, 'is_warm_up' => true],
            ]]],
        ])->assertSessionHasNoErrors();
        $this->post(route('routines.archive', $routine));

        $this->assertTrue($workout->refresh()->routine->is($routine));
        $this->assertSame([[$squat->id, [[5, '140.00', false], [5, '140.00', false]]]], $this->plannedSets($workout));
    }

    public function test_starting_while_a_workout_is_in_progress_creates_nothing_and_redirects_to_it()
    {
        $inProgress = Workout::factory()->create();
        $routine = Routine::factory()->for($inProgress->user)->withExercises(1, 2)->create();

        $response = $this->actingAs($inProgress->user)->post(route('routines.start', $routine));

        $response
            ->assertRedirect(route('workouts.show', $inProgress))
            ->assertInertiaFlash('toast.message', 'You already have a Workout in progress.');

        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseEmpty('workout_exercises');
    }

    public function test_an_archived_routine_cannot_be_started()
    {
        $routine = Routine::factory()->withExercises(1, 2)->archived()->create();

        $response = $this->actingAs($routine->user)->post(route('routines.start', $routine));

        $response->assertForbidden();

        $this->assertDatabaseEmpty('workouts');
    }

    /**
     * Plan an Exercise after the Routine's others, with Sets of [reps, weight, warm-up].
     *
     * @param  list<array{int, string, bool}>  $sets
     */
    private function plan(Routine $routine, array $sets, ?Exercise $exercise = null): Exercise
    {
        $exercise ??= Exercise::factory()->for($routine->user)->create();

        RoutineExercise::factory()
            ->for($routine)
            ->for($exercise)
            ->has(
                RoutineSet::factory()
                    ->count(count($sets))
                    ->sequence(fn (Sequence $sequence) => [
                        'position' => $sequence->index,
                        'target_reps' => $sets[$sequence->index][0],
                        'target_weight' => $sets[$sequence->index][1],
                        'is_warm_up' => $sets[$sequence->index][2],
                    ]),
                'sets',
            )
            ->create(['position' => $routine->exercises()->count()]);

        return $exercise;
    }

    /**
     * A finished Workout of the owner that started the given time ago, ad hoc unless a Routine is given.
     */
    private function finishedWorkout(User $owner, string $startedAgo, ?Routine $routine = null): Workout
    {
        return Workout::factory()->for($owner)->finished()->create([
            'routine_id' => $routine?->id,
            'started_at' => now()->modify($startedAgo),
        ]);
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

    private function workoutInProgress(User $owner): Workout
    {
        return $owner->workouts()->inProgress()->sole();
    }

    /**
     * The started Workout's Exercises in order, each with its Sets as [Target reps, Target weight, warm-up].
     *
     * @return list<array{int, list<array{int|null, string|null, bool}>}>
     */
    private function plannedSets(Workout $workout): array
    {
        return $workout->exercises()->with('sets')->get()
            ->map(fn (WorkoutExercise $performed) => [
                $performed->exercise_id,
                $performed->sets->map(fn (WorkoutSet $set) => [$set->target_reps, $set->target_weight, $set->is_warm_up])->all(),
            ])
            ->all();
    }
}
