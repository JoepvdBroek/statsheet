<?php

namespace Tests\Feature\Workouts;

use App\Enums\SetKind;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Factories\ExerciseFactory;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkoutLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_set_done_logs_the_typed_actual()
    {
        $set = $this->setOf(WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => 80]));

        $response = $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]), ['actual_reps' => 6, 'actual_weight' => '82.25']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $this->workout($set)));

        $set->refresh();
        $this->assertSame(6, $set->actual_reps);
        $this->assertSame('82.25', $set->actual_weight);
        $this->assertTrue($set->isDone());
    }

    public function test_marking_a_set_done_with_nothing_typed_saves_the_target_as_the_actual()
    {
        $set = $this->setOf(WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => 80]));

        $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]))
            ->assertSessionHasNoErrors();

        $set->refresh();
        $this->assertSame(8, $set->actual_reps);
        $this->assertSame('80.00', $set->actual_weight);
    }

    public function test_a_value_not_typed_comes_from_the_target()
    {
        $set = $this->setOf(WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => 80]));

        $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]), ['actual_reps' => 7, 'actual_weight' => null]);

        $set->refresh();
        $this->assertSame(7, $set->actual_reps);
        $this->assertSame('80.00', $set->actual_weight);
    }

    public function test_a_set_without_a_target_needs_its_reps_and_weight_typed()
    {
        $set = $this->setOf(WorkoutSet::factory()->withoutTarget());

        $response = $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]));

        $response->assertSessionHasErrors([
            'actual_reps' => 'Enter the reps you did.',
            'actual_weight' => 'Enter the weight you lifted.',
        ]);
        $this->assertFalse($set->refresh()->isDone());
    }

    public function test_a_bodyweight_exercise_set_without_a_target_logs_no_added_load()
    {
        $set = $this->setOf(WorkoutSet::factory()->withoutTarget(), Exercise::factory()->bodyweight());

        $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]), ['actual_reps' => 12])
            ->assertSessionHasNoErrors();

        $set->refresh();
        $this->assertSame(12, $set->actual_reps);
        $this->assertSame('0.00', $set->actual_weight);
    }

    public function test_un_marking_a_set_clears_its_actual()
    {
        $set = $this->setOf(WorkoutSet::factory()->done());

        $response = $this
            ->actingAs($this->owner($set))
            ->delete(route('workouts.sets.undone', [$this->workout($set), $set]));

        $response->assertRedirect(route('workouts.show', $this->workout($set)));

        $set->refresh();
        $this->assertNull($set->actual_reps);
        $this->assertNull($set->actual_weight);
        $this->assertFalse($set->isDone());
        $this->assertNotNull($set->target_reps);
    }

    public function test_correcting_a_done_sets_actual_replaces_it()
    {
        $set = $this->setOf(WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => 80])->done());

        $response = $this
            ->actingAs($this->owner($set))
            ->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['actual_reps' => 7, 'actual_weight' => '77.5']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $this->workout($set)));

        $set->refresh();
        $this->assertSame(7, $set->actual_reps);
        $this->assertSame('77.50', $set->actual_weight);
        $this->assertSame(8, $set->target_reps);
    }

    public function test_correcting_an_actual_needs_both_reps_and_weight()
    {
        $set = $this->setOf(WorkoutSet::factory()->done());

        $response = $this
            ->actingAs($this->owner($set))
            ->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['actual_reps' => 7]);

        $response->assertSessionHasErrors(['actual_weight' => 'The weight field is required when reps is present.']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidActuals(): array
    {
        return [
            'zero reps' => [['actual_reps' => 0, 'actual_weight' => 80], 'actual_reps', 'The reps field must be at least 1.'],
            'fractional reps' => [['actual_reps' => 7.5, 'actual_weight' => 80], 'actual_reps', 'The reps field must be an integer.'],
            'negative weight' => [['actual_reps' => 8, 'actual_weight' => -2.5], 'actual_weight', 'The weight field must be at least 0.'],
            'three decimals' => [['actual_reps' => 8, 'actual_weight' => '80.125'], 'actual_weight', 'The weight field must have 0-2 decimal places.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    #[DataProvider('invalidActuals')]
    public function test_marking_done_rejects_an_invalid_actual(array $actual, string $field, string $message)
    {
        $set = $this->setOf(WorkoutSet::factory());

        $response = $this
            ->actingAs($this->owner($set))
            ->put(route('workouts.sets.done', [$this->workout($set), $set]), $actual);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertFalse($set->refresh()->isDone());
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    #[DataProvider('invalidActuals')]
    public function test_correcting_rejects_an_invalid_actual(array $actual, string $field, string $message)
    {
        $set = $this->setOf(WorkoutSet::factory()->state(['target_reps' => 8, 'target_weight' => 80])->done());

        $response = $this
            ->actingAs($this->owner($set))
            ->patch(route('workouts.sets.update', [$this->workout($set), $set]), $actual);

        $response->assertSessionHasErrors([$field => $message]);
        $this->assertSame(8, $set->refresh()->actual_reps);
    }

    public function test_the_kind_of_any_set_can_be_changed()
    {
        $set = $this->setOf(WorkoutSet::factory()->done());
        $actualReps = $set->actual_reps;

        $this->actingAs($this->owner($set));

        $this->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['kind' => 'warm_up'])
            ->assertSessionHasNoErrors();
        $this->assertSame(SetKind::WarmUp, $set->refresh()->kind);
        $this->assertSame($actualReps, $set->actual_reps);

        $this->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['kind' => 'drop']);
        $this->assertSame(SetKind::Drop, $set->refresh()->kind);

        $this->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['kind' => 'working']);
        $this->assertSame(SetKind::Working, $set->refresh()->kind);
    }

    public function test_an_unknown_kind_is_rejected()
    {
        $set = $this->setOf(WorkoutSet::factory()->drop());

        $response = $this
            ->actingAs($this->owner($set))
            ->patch(route('workouts.sets.update', [$this->workout($set), $set]), ['kind' => 'superset']);

        $response->assertSessionHasErrors(['kind' => 'The selected kind is invalid.']);
        $this->assertSame(SetKind::Drop, $set->refresh()->kind);
    }

    public function test_the_logging_screen_shows_each_sets_target_actual_and_status()
    {
        $workout = Workout::factory()->create();
        $exercise = WorkoutExercise::factory()->for($workout)->create();
        WorkoutSet::factory()->for($exercise)->warmUp()->done()->create(['position' => 0]);
        WorkoutSet::factory()->for($exercise)->state(['position' => 1, 'target_reps' => 8, 'target_weight' => 80])->missed()->create();
        WorkoutSet::factory()->for($exercise)->notDone()->create(['position' => 2]);
        WorkoutSet::factory()->for($exercise)->withoutTarget()->create(['position' => 3]);

        $response = $this->actingAs($workout->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workout.exercises.0.exercise.id', $exercise->exercise_id)
            ->where('workout.exercises.0.sets.0.kind', 'warm_up')
            ->where('workout.exercises.0.sets.0.done', true)
            ->where('workout.exercises.0.sets.0.meets_target', true)
            ->where('workout.exercises.0.sets.1.target_reps', 8)
            ->where('workout.exercises.0.sets.1.target_weight', 80)
            ->where('workout.exercises.0.sets.1.actual_reps', 6)
            ->where('workout.exercises.0.sets.1.actual_weight', 80)
            ->where('workout.exercises.0.sets.1.done', true)
            ->where('workout.exercises.0.sets.1.meets_target', false)
            ->where('workout.exercises.0.sets.2.done', false)
            ->where('workout.exercises.0.sets.2.actual_reps', null)
            ->where('workout.exercises.0.sets.3.target_reps', null)
            ->where('workout.exercises.0.sets.3.done', false)
        );
    }

    public function test_the_workout_note_can_be_written_and_cleared()
    {
        $workout = Workout::factory()->create();

        $this->actingAs($workout->user);

        $this->patch(route('workouts.update', $workout), ['note' => 'Deload week, left knee sore'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('workouts.show', $workout));
        $this->assertSame('Deload week, left knee sore', $workout->refresh()->note);

        $this->patch(route('workouts.update', $workout), ['note' => '']);
        $this->assertNull($workout->refresh()->note);
    }

    public function test_the_note_of_a_finished_workout_can_still_be_edited()
    {
        $workout = Workout::factory()->finished()->create();

        $this->actingAs($workout->user)
            ->patch(route('workouts.update', $workout), ['note' => 'Bad sleep'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bad sleep', $workout->refresh()->note);
    }

    public function test_the_note_is_at_most_5000_characters()
    {
        $workout = Workout::factory()->create();

        $response = $this
            ->actingAs($workout->user)
            ->patch(route('workouts.update', $workout), ['note' => str_repeat('a', 5001)]);

        $response->assertSessionHasErrors(['note' => 'The note field must not be greater than 5000 characters.']);
    }

    public function test_finishing_sets_the_finish_time_and_the_status_to_finished()
    {
        $this->freezeSecond();
        $workout = Workout::factory()->create(['started_at' => now()->subHour()]);

        $response = $this->actingAs($workout->user)->post(route('workouts.finish', $workout));

        $response
            ->assertRedirect(route('home'))
            ->assertInertiaFlash('toast.message', 'Workout finished.');

        $this->assertTrue($workout->refresh()->finished_at->equalTo(now()));

        $this->get(route('workouts.show', $workout))
            ->assertInertia(fn (Assert $page) => $page->where('workout.status', 'finished'));
    }

    public function test_finishing_again_keeps_the_first_finish_time()
    {
        $workout = Workout::factory()->finished()->create();
        $finishedAt = $workout->finished_at;

        $this->travel(2)->hours();
        $this->actingAs($workout->user)->post(route('workouts.finish', $workout));

        $this->assertTrue($workout->refresh()->finished_at->equalTo($finishedAt));
    }

    /**
     * Creates a Set, alone in an Exercise of a Workout in progress.
     */
    private function setOf(WorkoutSetFactory $set, ?ExerciseFactory $exercise = null): WorkoutSet
    {
        $workout = Workout::factory()->create();
        $exercise = ($exercise ?? Exercise::factory())->for($workout->user)->create();

        return $set
            ->for(WorkoutExercise::factory()->for($workout)->for($exercise))
            ->create();
    }

    private function workout(WorkoutSet $set): Workout
    {
        return $set->workoutExercise->workout;
    }

    private function owner(WorkoutSet $set): User
    {
        return $this->workout($set)->user;
    }
}
