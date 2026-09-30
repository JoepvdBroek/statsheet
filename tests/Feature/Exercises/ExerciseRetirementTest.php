<?php

namespace Tests\Feature\Exercises;

use App\Models\Exercise;
use App\Models\Routine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_unused_exercise_removes_it_with_its_muscles()
    {
        $exercise = Exercise::factory()->withSecondaryMuscle()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->delete(route('exercises.destroy', $exercise));

        $response
            ->assertRedirect(route('exercises.index'))
            ->assertInertiaFlash('toast.message', 'Exercise deleted.');

        $this->assertModelMissing($exercise);
        $this->assertDatabaseEmpty('exercise_muscles');
    }

    public function test_deleting_an_exercise_a_routine_uses_archives_it_and_keeps_the_routine_plan()
    {
        $routine = Routine::factory()->withExercises(1, 2)->create();
        $exercise = $routine->exercises[0]->exercise;

        $response = $this
            ->actingAs($exercise->user)
            ->delete(route('exercises.destroy', $exercise));

        $response
            ->assertRedirect(route('exercises.index'))
            ->assertInertiaFlash('toast.message', 'Exercise archived, because a Routine or Workout uses it.');

        $this->assertNotNull($exercise->refresh()->archived_at);
        $this->assertDatabaseCount('routine_sets', 2);
    }

    public function test_deleting_an_exercise_an_archived_routine_uses_archives_it()
    {
        $routine = Routine::factory()->withExercises(1, 1)->archived()->create();
        $exercise = $routine->exercises[0]->exercise;

        $this
            ->actingAs($exercise->user)
            ->delete(route('exercises.destroy', $exercise));

        $this->assertNotNull($exercise->refresh()->archived_at);
    }

    public function test_restoring_an_archived_exercise_puts_it_back_in_use()
    {
        $exercise = Exercise::factory()->archived()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->post(route('exercises.restore', $exercise));

        $response
            ->assertRedirect(route('exercises.index', ['archived' => 1]))
            ->assertInertiaFlash('toast.message', 'Exercise restored.');

        $this->assertNull($exercise->refresh()->archived_at);
    }
}
