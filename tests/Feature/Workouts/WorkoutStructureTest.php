<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->assertFalse($set->is_warm_up);
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

    public function test_adding_a_set_appends_one_without_a_target()
    {
        $workout = Workout::factory()->withExercises(1, 2)->create();
        $exercise = $workout->exercises[0];

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.sets.store', [$workout, $exercise]));

        $response->assertRedirect(route('workouts.show', $workout));

        $sets = $exercise->sets()->get();
        $this->assertCount(3, $sets);
        $this->assertSame(2, $sets[2]->position);
        $this->assertNull($sets[2]->target_reps);
        $this->assertNull($sets[2]->target_weight);
        $this->assertFalse($sets[2]->isDone());
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
}
