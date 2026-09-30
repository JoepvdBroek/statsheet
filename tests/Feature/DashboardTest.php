<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_the_dashboard_offers_to_resume_the_workout_in_progress()
    {
        $workout = Workout::factory()->create(['started_at' => '2026-09-30 16:05:00']);

        $response = $this->actingAs($workout->user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('workoutInProgress.id', $workout->id)
            ->where('workoutInProgress.started_at', '2026-09-30T16:05:00+00:00')
        );
    }

    public function test_the_dashboard_offers_to_start_an_empty_workout_when_none_is_in_progress()
    {
        $finished = Workout::factory()->finished()->create();
        Workout::factory()->create();

        $response = $this->actingAs($finished->user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page->where('workoutInProgress', null));
    }
}
