<?php

namespace Tests\Feature\Exercises;

use App\Models\Exercise;
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
