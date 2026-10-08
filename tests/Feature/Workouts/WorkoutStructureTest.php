<?php

namespace Tests\Feature\Workouts;

use App\Enums\SetKind;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkoutStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_picker_offers_only_the_owners_exercises_in_use()
    {
        $workout = Workout::factory()->create();
        Exercise::factory()->for($workout->user)->create(['name' => 'Squat']);
        Exercise::factory()->for($workout->user)->archived()->create();
        Exercise::factory()->create();

        $response = $this->actingAs($workout->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('exercises', 1)
                ->where('exercises.0.name', 'Squat')
            )
        );
    }

    public function test_adding_an_exercise_appends_it_with_one_set_without_a_target()
    {
        $workout = Workout::factory()->withExercises(1, 1)->create();
        $squat = Exercise::factory()->for($workout->user)->create(['name' => 'Squat']);

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.store', $workout), ['exercise_id' => $squat->id]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $workout));

        $added = $workout->exercises()->get()->last();
        $this->assertSame($squat->id, $added->exercise_id);
        $this->assertSame(1, $added->position);

        $set = $added->sets()->sole();
        $this->assertNull($set->target_reps);
        $this->assertNull($set->target_weight);
        $this->assertFalse($set->isDone());
        $this->assertSame(SetKind::Working, $set->kind);
    }

    public function test_the_same_exercise_can_be_added_twice()
    {
        $workout = Workout::factory()->create();
        $squat = Exercise::factory()->for($workout->user)->create();

        $this->actingAs($workout->user);
        $this->post(route('workouts.exercises.store', $workout), ['exercise_id' => $squat->id]);
        $this->post(route('workouts.exercises.store', $workout), ['exercise_id' => $squat->id]);

        $this->assertSame([$squat->id, $squat->id], $workout->exercises()->pluck('exercise_id')->all());
    }

    public function test_an_archived_exercise_cannot_be_added()
    {
        $workout = Workout::factory()->create();
        $archived = Exercise::factory()->for($workout->user)->archived()->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.store', $workout), ['exercise_id' => $archived->id]);

        $response->assertSessionHasErrors(['exercise_id' => 'Choose one of your Exercises in use.']);
        $this->assertDatabaseEmpty('workout_exercises');
    }

    public function test_another_users_exercise_cannot_be_added()
    {
        $workout = Workout::factory()->create();
        $someoneElses = Exercise::factory()->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.store', $workout), ['exercise_id' => $someoneElses->id]);

        $response->assertSessionHasErrors(['exercise_id' => 'Choose one of your Exercises in use.']);
        $this->assertDatabaseEmpty('workout_exercises');
    }

    public function test_removing_an_exercise_removes_its_sets()
    {
        $workout = Workout::factory()->withExercises(2, 3)->create();
        [$first, $second] = $workout->exercises;

        $response = $this
            ->actingAs($workout->user)
            ->delete(route('workouts.exercises.destroy', [$workout, $first]));

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertModelMissing($first);
        $this->assertSame([$second->id], $workout->exercises()->pluck('id')->all());
        $this->assertDatabaseCount('workout_sets', 3);
    }

    public function test_moving_an_exercise_changes_the_order()
    {
        $workout = Workout::factory()->withExercises(3, 1)->create();
        [$first, $second, $third] = $workout->exercises;

        $response = $this
            ->actingAs($workout->user)
            ->put(route('workouts.exercises.move', [$workout, $third]), ['position' => 0]);

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertSame([$third->id, $first->id, $second->id], $workout->exercises()->pluck('id')->all());
    }

    public function test_moving_past_the_end_moves_to_the_end()
    {
        $workout = Workout::factory()->withExercises(3, 1)->create();
        [$first, $second, $third] = $workout->exercises;

        $this
            ->actingAs($workout->user)
            ->put(route('workouts.exercises.move', [$workout, $first]), ['position' => 10]);

        $this->assertSame([$second->id, $third->id, $first->id], $workout->exercises()->pluck('id')->all());
    }

    public function test_a_position_must_be_zero_or_more()
    {
        $workout = Workout::factory()->withExercises(2, 1)->create();

        $response = $this
            ->actingAs($workout->user)
            ->put(route('workouts.exercises.move', [$workout, $workout->exercises[1]]), ['position' => -1]);

        $response->assertSessionHasErrors(['position' => 'The position field must be at least 0.']);
    }

    /**
     * @return array<string, array{0: array{int|null, string|null, int|null, string|null}, 1: array{int|null, string|null}}>
     */
    public static function setsBefore(): array
    {
        return [
            'done: its Actual' => [[8, '100.00', 6, '102.50'], [6, '102.50']],
            'not done: its Target' => [[8, '100.00', null, null], [8, '100.00']],
            'neither: no Target' => [[null, null, null, null], [null, null]],
        ];
    }

    /**
     * @param  array{int|null, string|null, int|null, string|null}  $before
     * @param  array{int|null, string|null}  $expectedTarget
     */
    #[DataProvider('setsBefore')]
    public function test_adding_a_set_appends_a_working_set_targeting_the_set_before_it(array $before, array $expectedTarget)
    {
        $exercise = $this->performedWith([
            WorkoutSet::factory()->state([
                'target_reps' => $before[0],
                'target_weight' => $before[1],
                'actual_reps' => $before[2],
                'actual_weight' => $before[3],
            ]),
        ]);

        $response = $this
            ->actingAs($exercise->workout->user)
            ->post(route('workouts.sets.store', [$exercise->workout, $exercise]));

        $response->assertRedirect(route('workouts.show', $exercise->workout));

        $sets = $exercise->sets()->get();
        $this->assertCount(2, $sets);
        $this->assertSame(1, $sets[1]->position);
        $this->assertSame([...$expectedTarget, SetKind::Working], [$sets[1]->target_reps, $sets[1]->target_weight, $sets[1]->kind]);
        $this->assertFalse($sets[1]->isDone());
    }

    public function test_a_set_added_after_a_drop_set_is_a_drop_set()
    {
        $exercise = $this->performedWith([
            WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => '100.00'])->done(),
            WorkoutSet::factory()->withoutTarget()->drop()->state(['actual_reps' => 6, 'actual_weight' => '80.00']),
        ]);

        $this->actingAs($exercise->workout->user)->post(route('workouts.sets.store', [$exercise->workout, $exercise]));

        $added = $exercise->sets()->get()->last();
        $this->assertSame([6, '80.00', SetKind::Drop], [$added->target_reps, $added->target_weight, $added->kind]);
    }

    public function test_a_set_added_after_warm_ups_copies_the_last_set_that_is_not_a_warm_up()
    {
        $exercise = $this->performedWith([
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '100.00'])->drop(),
            WorkoutSet::factory()->state(['target_reps' => 10, 'target_weight' => '40.00'])->warmUp(),
        ]);

        $this->actingAs($exercise->workout->user)->post(route('workouts.sets.store', [$exercise->workout, $exercise]));

        $added = $exercise->sets()->get()->last();
        $this->assertSame([5, '100.00', SetKind::Drop], [$added->target_reps, $added->target_weight, $added->kind]);
    }

    public function test_a_set_added_after_only_warm_ups_copies_the_last_one_as_a_working_set()
    {
        $exercise = $this->performedWith([
            WorkoutSet::factory()->state(['target_reps' => 10, 'target_weight' => '40.00'])->warmUp(),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '60.00'])->warmUp(),
        ]);

        $this->actingAs($exercise->workout->user)->post(route('workouts.sets.store', [$exercise->workout, $exercise]));

        $added = $exercise->sets()->get()->last();
        $this->assertSame([5, '60.00', SetKind::Working], [$added->target_reps, $added->target_weight, $added->kind]);
    }

    public function test_the_first_set_added_has_no_target()
    {
        $exercise = $this->performedWith([]);

        $this->actingAs($exercise->workout->user)->post(route('workouts.sets.store', [$exercise->workout, $exercise]));

        $added = $exercise->sets()->sole();
        $this->assertSame([0, null, null, SetKind::Working], [$added->position, $added->target_reps, $added->target_weight, $added->kind]);
    }

    public function test_removing_a_set_keeps_the_others()
    {
        $workout = Workout::factory()->withExercises(1, 3)->create();
        [$first, $second, $third] = $workout->exercises[0]->sets;

        $response = $this
            ->actingAs($workout->user)
            ->delete(route('workouts.sets.destroy', [$workout, $second]));

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertSame([$first->id, $third->id], $workout->exercises[0]->sets()->pluck('id')->all());
    }

    public function test_moving_a_set_changes_the_order_within_its_exercise()
    {
        $workout = Workout::factory()->withExercises(2, 3)->create();
        [$first, $second, $third] = $workout->exercises[0]->sets;

        $response = $this
            ->actingAs($workout->user)
            ->put(route('workouts.sets.move', [$workout, $first]), ['position' => 1]);

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertSame([$second->id, $first->id, $third->id], $workout->exercises[0]->sets()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $workout->exercises[1]->sets()->pluck('position')->all());
    }

    public function test_an_exercise_or_set_of_another_workout_returns_404()
    {
        $workout = Workout::factory()->create();
        $otherWorkout = Workout::factory()->for($workout->user)->finished()->withExercises(1, 1)->create();
        $otherExercise = $otherWorkout->exercises[0];
        $otherSet = $otherExercise->sets[0];

        $this->actingAs($workout->user);

        $this->delete(route('workouts.exercises.destroy', [$workout, $otherExercise]))->assertNotFound();
        $this->post(route('workouts.sets.store', [$workout, $otherExercise]))->assertNotFound();
        $this->delete(route('workouts.sets.destroy', [$workout, $otherSet]))->assertNotFound();

        $this->assertModelExists($otherExercise);
        $this->assertModelExists($otherSet);
        $this->assertSame(1, WorkoutSet::count());
        $this->assertSame(1, WorkoutExercise::count());
    }

    /**
     * An Exercise in a Workout in progress, with the given Sets in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    private function performedWith(array $sets): WorkoutExercise
    {
        $exercise = WorkoutExercise::factory()->for(Workout::factory())->create(['position' => 0]);

        foreach ($sets as $position => $set) {
            $set->for($exercise)->create(['position' => $position]);
        }

        return $exercise;
    }
}
