<?php

namespace Tests\Feature\Routines;

use App\Models\Routine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineArchivingTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_routines_in_use_by_name_and_hides_archived_ones()
    {
        $owner = Routine::factory()->create(['name' => 'Push day'])->user;
        Routine::factory()->for($owner)->withExercises(2, 3)->create(['name' => 'Legs']);
        Routine::factory()->for($owner)->archived()->create(['name' => 'Old pull day']);
        Routine::factory()->create(['name' => 'Someone else\'s day']);

        $response = $this
            ->actingAs($owner)
            ->get(route('routines.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('routines/index')
            ->where('archived', false)
            ->has('routines', 2)
            ->where('routines.0.name', 'Legs')
            ->has('routines.0.exercises', 2)
            ->has('routines.0.exercises.1.sets', 3)
            ->where('routines.1.name', 'Push day')
        );
    }

    public function test_archived_list_shows_only_archived_routines()
    {
        $routine = Routine::factory()->archived()->create(['name' => 'Old pull day']);
        Routine::factory()->for($routine->user)->create();

        $response = $this
            ->actingAs($routine->user)
            ->get(route('routines.index', ['archived' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('archived', true)
            ->has('routines', 1)
            ->where('routines.0.name', 'Old pull day')
            ->where('routines.0.archived', true)
        );
    }

    public function test_archiving_a_routine_hides_it_and_keeps_its_plan()
    {
        $routine = Routine::factory()->withExercises(2, 3)->create();

        $response = $this
            ->actingAs($routine->user)
            ->post(route('routines.archive', $routine));

        $response
            ->assertRedirect(route('routines.index'))
            ->assertInertiaFlash('toast.message', 'Routine archived.');

        $this->assertNotNull($routine->refresh()->archived_at);
        $this->assertDatabaseCount('routine_exercises', 2);
        $this->assertDatabaseCount('routine_sets', 6);

        $this->get(route('routines.index'))
            ->assertInertia(fn (Assert $page) => $page->has('routines', 0));
    }

    public function test_restoring_an_archived_routine_brings_it_back_to_the_list()
    {
        $routine = Routine::factory()->archived()->create();

        $response = $this
            ->actingAs($routine->user)
            ->post(route('routines.restore', $routine));

        $response
            ->assertRedirect(route('routines.index', ['archived' => 1]))
            ->assertInertiaFlash('toast.message', 'Routine restored.');

        $this->assertNull($routine->refresh()->archived_at);

        $this->get(route('routines.index'))
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
