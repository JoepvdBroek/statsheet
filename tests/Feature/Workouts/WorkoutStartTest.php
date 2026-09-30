<?php

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkoutStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_an_empty_workout_records_the_start_time_and_a_bodyweight_snapshot()
    {
        $this->freezeSecond();
        $owner = User::factory()->create(['bodyweight' => '82.40']);

        $response = $this->actingAs($owner)->post(route('workouts.store'));

        $workout = $owner->workouts()->sole();

        $response->assertRedirect(route('workouts.show', $workout));

        $this->assertTrue($workout->started_at->equalTo(now()));
        $this->assertNull($workout->finished_at);
        $this->assertSame('82.40', $workout->bodyweight);
        $this->assertNull($workout->note);
        $this->assertDatabaseEmpty('workout_exercises');
    }

    public function test_the_bodyweight_snapshot_is_empty_when_the_owner_has_no_bodyweight()
    {
        $owner = User::factory()->create(['bodyweight' => null]);

        $this->actingAs($owner)->post(route('workouts.store'));

        $this->assertNull($owner->workouts()->sole()->bodyweight);
    }

    public function test_the_snapshot_is_kept_when_the_owner_changes_bodyweight_later()
    {
        $owner = User::factory()->create(['bodyweight' => '80.00']);
        $this->actingAs($owner)->post(route('workouts.store'));

        $owner->update(['bodyweight' => '85.00']);

        $this->assertSame('80.00', $owner->workouts()->sole()->bodyweight);
    }

    public function test_starting_while_a_workout_is_in_progress_creates_nothing_and_redirects_to_it()
    {
        $inProgress = Workout::factory()->create();

        $response = $this->actingAs($inProgress->user)->post(route('workouts.store'));

        $response
            ->assertRedirect(route('workouts.show', $inProgress))
            ->assertInertiaFlash('toast.message', 'You already have a Workout in progress.');

        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_a_finished_workout_does_not_stop_a_new_one_from_starting()
    {
        $finished = Workout::factory()->finished()->create();

        $this->actingAs($finished->user)->post(route('workouts.store'));

        $this->assertSame(1, $finished->user->workouts()->inProgress()->count());
        $this->assertDatabaseCount('workouts', 2);
    }

    public function test_another_users_workout_in_progress_does_not_count()
    {
        Workout::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('workouts.store'));

        $this->assertSame(1, $owner->workouts()->count());
    }

    public function test_the_logging_screen_shows_the_workout_in_progress()
    {
        $workout = Workout::factory()->create(['bodyweight' => '82.40', 'note' => 'Bad sleep']);

        $response = $this->actingAs($workout->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('workouts/show')
            ->where('workout.id', $workout->id)
            ->where('workout.status', 'in_progress')
            ->where('workout.finished_at', null)
            ->where('workout.bodyweight', 82.4)
            ->where('workout.note', 'Bad sleep')
            ->has('workout.exercises', 0)
            ->missing('exercises')
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('exercises'))
        );
    }
}
