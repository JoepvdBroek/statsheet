<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_the_owners_workouts_newest_first_with_routine_and_note()
    {
        $owner = User::factory()->create();
        $routine = Routine::factory()->for($owner)->archived()->create(['name' => 'Old push day']);
        $oldest = Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-20 18:00:00', 'note' => 'Bad sleep']);
        $newest = Workout::factory()->for($owner)->create(['started_at' => '2026-09-29 18:00:00']);
        $middle = Workout::factory()->for($owner)->for($routine)->finished()->create(['started_at' => '2026-09-25 18:00:00']);
        Workout::factory()->create(['started_at' => '2026-09-27 18:00:00']);

        $response = $this->actingAs($owner)->get(route('workouts.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('workouts/index')
            ->has('workouts.data', 3)
            ->where('workouts.data.0.id', $newest->id)
            ->where('workouts.data.0.status', 'in_progress')
            ->where('workouts.data.0.routine', null)
            ->where('workouts.data.1', [
                'id' => $middle->id,
                'status' => 'finished',
                'started_at' => '2026-09-25T18:00:00+00:00',
                'routine' => ['id' => $routine->id, 'name' => 'Old push day', 'archived' => true],
                'note' => null,
            ])
            ->where('workouts.data.2.id', $oldest->id)
            ->where('workouts.data.2.note', 'Bad sleep')
        );
    }

    public function test_a_finished_workouts_set_can_be_corrected_and_it_stays_finished()
    {
        $workout = Workout::factory()->finished()->create();
        $set = WorkoutSet::factory()
            ->for(WorkoutExercise::factory()->for($workout), 'workoutExercise')
            ->state(['target_reps' => 5, 'target_weight' => '100.00'])
            ->done()
            ->create();
        $finishedAt = $workout->finished_at;

        $response = $this
            ->actingAs($workout->user)
            ->patch(route('workouts.sets.update', [$workout, $set]), ['actual_reps' => 4, 'actual_weight' => '100']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $workout));

        $this->assertSame([4, '100.00'], [$set->refresh()->actual_reps, $set->actual_weight]);
        $this->assertTrue($workout->refresh()->finished_at->equalTo($finishedAt));
    }

    public function test_an_exercise_can_be_added_to_a_finished_workout()
    {
        $workout = Workout::factory()->finished()->create();
        $squat = Exercise::factory()->for($workout->user)->create();

        $response = $this
            ->actingAs($workout->user)
            ->post(route('workouts.exercises.store', $workout), ['exercise_id' => $squat->id]);

        $response->assertSessionHasNoErrors();

        $this->assertSame([$squat->id], $workout->exercises()->pluck('exercise_id')->all());
        $this->assertFalse($workout->refresh()->isInProgress());
    }

    public function test_a_finished_workouts_note_can_be_changed()
    {
        $workout = Workout::factory()->finished()->create(['note' => 'Bad sleep']);

        $response = $this
            ->actingAs($workout->user)
            ->patch(route('workouts.update', $workout), ['note' => 'Deload week']);

        $response->assertSessionHasNoErrors();

        $this->assertSame('Deload week', $workout->refresh()->note);
        $this->assertFalse($workout->isInProgress());
    }

    public function test_deleting_a_workout_removes_it_with_its_exercises_and_sets()
    {
        $workout = Workout::factory()->withExercises(2, 3)->finished()->create();
        $kept = Workout::factory()->for($workout->user)->withExercises(1, 2)->finished()->create();

        $response = $this->actingAs($workout->user)->delete(route('workouts.destroy', $workout));

        $response
            ->assertRedirect(route('home'))
            ->assertInertiaFlash('toast.message', 'Workout deleted.');

        $this->assertModelMissing($workout);
        $this->assertModelExists($kept);
        $this->assertDatabaseCount('workout_exercises', 1);
        $this->assertDatabaseCount('workout_sets', 2);
    }

    public function test_deleting_the_workout_in_progress_lets_a_new_one_start()
    {
        $inProgress = Workout::factory()->create();
        $this->actingAs($inProgress->user)->delete(route('workouts.destroy', $inProgress));

        $this->post(route('workouts.store'));

        $this->assertModelMissing($inProgress);
        $this->assertSame(1, $inProgress->user->workouts()->inProgress()->count());
    }
}
