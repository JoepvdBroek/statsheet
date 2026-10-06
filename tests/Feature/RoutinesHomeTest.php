<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RoutinesHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_their_routines_in_use_by_name()
    {
        $owner = Routine::factory()->create(['name' => 'Push day'])->user;
        Routine::factory()->for($owner)->withExercises(2, 3)->create(['name' => 'Legs']);
        Routine::factory()->for($owner)->archived()->create(['name' => 'Old pull day']);
        Routine::factory()->create(['name' => 'Someone else\'s day']);

        $response = $this->actingAs($owner)->get('/');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('routines', 2)
            ->where('routines.0.name', 'Legs')
            ->has('routines.0.exercises', 2)
            ->has('routines.0.exercises.1.sets', 3)
            ->where('routines.1.name', 'Push day')
            ->where('archivedRoutines', null)
        );
    }

    public function test_archived_routines_are_shown_only_when_asked_for()
    {
        $routine = Routine::factory()->archived()->create(['name' => 'Old pull day']);
        Routine::factory()->for($routine->user)->create(['name' => 'Legs']);

        $response = $this->actingAs($routine->user)->get(route('home', ['archived' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('routines', 1)
            ->where('routines.0.name', 'Legs')
            ->has('archivedRoutines', 1)
            ->where('archivedRoutines.0.name', 'Old pull day')
            ->where('archivedRoutines.0.archived', true)
        );
    }

    public function test_the_home_offers_to_resume_the_workout_in_progress()
    {
        $routine = Routine::factory()->create(['name' => 'Push day']);
        $workout = Workout::factory()->for($routine->user)->for($routine)->create(['started_at' => '2026-09-30 16:05:00']);

        $response = $this->actingAs($workout->user)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workoutInProgress.id', $workout->id)
            ->where('workoutInProgress.started_at', '2026-09-30T16:05:00+00:00')
            ->where('workoutInProgress.routine_name', 'Push day')
        );
    }

    public function test_the_resume_banner_has_no_routine_name_for_an_empty_workout()
    {
        $workout = Workout::factory()->create();

        $response = $this->actingAs($workout->user)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workoutInProgress.id', $workout->id)
            ->where('workoutInProgress.routine_name', null)
        );
    }

    public function test_there_is_nothing_to_resume_when_no_workout_is_in_progress()
    {
        $finished = Workout::factory()->finished()->create();
        Workout::factory()->create();

        $response = $this->actingAs($finished->user)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page->where('workoutInProgress', null));
    }

    public function test_the_home_nudges_to_set_a_bodyweight_while_it_is_empty()
    {
        $owner = User::factory()->create(['bodyweight' => null]);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page->where('bodyweightNudge', true));
    }

    public function test_the_home_does_not_nudge_once_a_bodyweight_is_set()
    {
        $owner = User::factory()->create(['bodyweight' => '80.00']);

        $response = $this->actingAs($owner)->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page->where('bodyweightNudge', false));
    }

    #[TestWith(['/dashboard'])]
    #[TestWith(['/routines'])]
    public function test_old_links_redirect_permanently_to_the_home(string $oldLink)
    {
        $response = $this->get($oldLink);

        $response->assertMovedPermanently()->assertRedirect(route('home'));
    }
}
