<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedWorkoutInProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_knows_the_workout_in_progress()
    {
        $workout = Workout::factory()->create();

        $response = $this->actingAs($workout->user)->get(route('goals.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('goals/index')
            ->where('workoutInProgressId', $workout->id)
        );
    }

    public function test_the_workout_in_progress_is_null_when_none_is_running()
    {
        $owner = User::factory()->create();
        Workout::factory()->finished()->for($owner)->create();
        Workout::factory()->create();

        $response = $this->actingAs($owner)->get(route('goals.index'));

        $response->assertInertia(fn (Assert $page) => $page->where('workoutInProgressId', null));
    }
}
