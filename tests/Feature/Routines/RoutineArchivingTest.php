<?php

namespace Tests\Feature\Routines;

use App\Models\Routine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineArchivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_a_routine_hides_it_and_keeps_its_plan()
    {
        $routine = Routine::factory()->withExercises(2, 3)->create();

        $response = $this
            ->actingAs($routine->user)
            ->post(route('routines.archive', $routine));

        $response
            ->assertRedirect(route('home'))
            ->assertInertiaFlash('toast.message', 'Routine archived.');

        $this->assertNotNull($routine->refresh()->archived_at);
        $this->assertDatabaseCount('routine_exercises', 2);
        $this->assertDatabaseCount('routine_sets', 6);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('routines', 0));
    }

    public function test_restoring_an_archived_routine_brings_it_back_to_the_home()
    {
        $routine = Routine::factory()->archived()->create();

        $response = $this
            ->actingAs($routine->user)
            ->post(route('routines.restore', $routine));

        $response
            ->assertRedirect(route('home', ['archived' => 1]))
            ->assertInertiaFlash('toast.message', 'Routine restored.');

        $this->assertNull($routine->refresh()->archived_at);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('routines', 1));
    }

    public function test_routines_cannot_be_deleted()
    {
        $routine = Routine::factory()->create();

        $response = $this
            ->actingAs($routine->user)
            ->delete("/routines/{$routine->id}");

        $response->assertMethodNotAllowed();

        $this->assertModelExists($routine);
    }
}
