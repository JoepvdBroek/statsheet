<?php

namespace Tests\Feature\Exercises;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExerciseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('exercises.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_lists_the_owners_active_exercises_by_name_with_their_muscles()
    {
        $owner = User::factory()->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->withSecondaryMuscle(Muscle::Glutes)
            ->create(['name' => 'Squat', 'equipment' => Equipment::Barbell]);
        Exercise::factory()->for($owner)->create(['name' => 'Deadlift']);
        Exercise::factory()->for($owner)->archived()->create(['name' => 'Archived row']);
        Exercise::factory()->create(['name' => 'Someone else\'s curl']);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('exercises/index')
            ->tap($this->listsExactly(['Deadlift', 'Squat']))
            ->where('exercises.data.1', [
                'id' => $squat->id,
                'name' => 'Squat',
                'equipment' => 'barbell',
                'is_bodyweight' => false,
                'archived' => false,
                'muscles' => [
                    ['muscle' => 'quadriceps', 'role' => 'primary'],
                    ['muscle' => 'glutes', 'role' => 'secondary'],
                ],
            ])
            ->where('filters', ['search' => null, 'muscle' => null, 'equipment' => null, 'archived' => false])
            ->has('muscles', 17)
            ->has('equipment', 12)
        );
    }

    public function test_search_matches_part_of_the_name_in_any_case()
    {
        $owner = User::factory()->create();
        Exercise::factory()->for($owner)->create(['name' => 'Barbell bench press']);
        Exercise::factory()->for($owner)->create(['name' => 'Incline Bench Press']);
        Exercise::factory()->for($owner)->create(['name' => 'Squat']);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index', ['search' => 'BENCH']));

        $response->assertInertia(fn (Assert $page) => $page
            ->tap($this->listsExactly(['Barbell bench press', 'Incline Bench Press']))
            ->where('filters.search', 'BENCH')
        );
    }

    public function test_muscle_filter_matches_primary_and_secondary_muscles()
    {
        $owner = User::factory()->create();
        Exercise::factory()->for($owner)->primaryMuscle(Muscle::Triceps)->create(['name' => 'Dip']);
        Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create(['name' => 'Bench press']);
        Exercise::factory()->for($owner)->primaryMuscle(Muscle::Biceps)->create(['name' => 'Curl']);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index', ['muscle' => 'triceps']));

        $response->assertInertia(fn (Assert $page) => $page
            ->tap($this->listsExactly(['Bench press', 'Dip']))
            ->where('filters.muscle', 'triceps')
        );
    }

    public function test_equipment_filter_matches_the_exercises_equipment()
    {
        $owner = User::factory()->create();
        Exercise::factory()->for($owner)->create(['name' => 'Dumbbell row', 'equipment' => Equipment::Dumbbell]);
        Exercise::factory()->for($owner)->create(['name' => 'Barbell row', 'equipment' => Equipment::Barbell]);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index', ['equipment' => 'dumbbell']));

        $response->assertInertia(fn (Assert $page) => $page
            ->tap($this->listsExactly(['Dumbbell row']))
            ->where('filters.equipment', 'dumbbell')
        );
    }

    public function test_archived_exercises_are_listed_only_when_asked_for()
    {
        $owner = User::factory()->create();
        Exercise::factory()->for($owner)->create(['name' => 'Squat']);
        Exercise::factory()->for($owner)->archived()->create(['name' => 'Good morning']);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index', ['archived' => 1]));

        $response->assertInertia(fn (Assert $page) => $page
            ->tap($this->listsExactly(['Good morning']))
            ->where('exercises.data.0.archived', true)
            ->where('filters.archived', true)
        );
    }

    public function test_unknown_filter_values_are_ignored()
    {
        $owner = User::factory()->create();
        Exercise::factory()->for($owner)->create(['name' => 'Squat']);

        $response = $this
            ->actingAs($owner)
            ->get(route('exercises.index', ['muscle' => 'arms', 'equipment' => 'trap bar']));

        $response->assertInertia(fn (Assert $page) => $page
            ->tap($this->listsExactly(['Squat']))
            ->where('filters.muscle', null)
            ->where('filters.equipment', null)
        );
    }

    /**
     * Assert the page lists exactly these Exercise names, in this order.
     *
     * @param  array<int, string>  $names
     * @return Closure(Assert): void
     */
    private function listsExactly(array $names): Closure
    {
        return function (Assert $page) use ($names) {
            $page->has('exercises.data', count($names));

            foreach ($names as $position => $name) {
                $page->where("exercises.data.{$position}.name", $name);
            }
        };
    }
}
