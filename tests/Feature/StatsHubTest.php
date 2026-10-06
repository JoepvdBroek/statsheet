<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('statistics.hub'));

        $response->assertRedirect(route('login'));
    }

    public function test_the_owner_sees_the_stats_hub()
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page->component('statistics/hub'));
    }
}
