<?php

namespace Tests\Feature;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\Goal;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_a_goal_puts_it_in_force_from_this_week()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);

        $response = $this->actingAs($owner)->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => '5000']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goals.index'))
            ->assertInertiaFlash('toast.message', 'Goal saved.');

        $goal = $owner->goals()->sole();
        $this->assertSame(Muscle::Chest, $goal->muscle);
        $this->assertSame('5000.00', $goal->weekly_minimum);
        $this->assertSame('2026-09-28', $goal->effective_week->toDateString());

        $this->get(route('statistics.hub'))->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'chest', 'volume' => 0, 'goal' => 5000, 'met' => false]])
        );
    }

    public function test_muscles_without_a_goal_still_show_their_volume_next_to_those_with_one()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '800.00', 'effective_week' => '2026-09-28']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Lats, 'weekly_minimum' => '3000.00', 'effective_week' => '2026-09-28']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $workout = Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 10:00:00']);
        WorkoutSet::factory()
            ->for(WorkoutExercise::factory()->for($workout)->for($benchPress), 'workoutExercise')
            ->state(['target_reps' => 10, 'target_weight' => '100.00'])
            ->done()
            ->create();

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [
                ['muscle' => 'chest', 'volume' => 1000, 'goal' => 800, 'met' => true],
                ['muscle' => 'triceps', 'volume' => 500, 'goal' => null, 'met' => null],
                ['muscle' => 'lats', 'volume' => 0, 'goal' => 3000, 'met' => false],
            ])
        );
    }

    public function test_a_goal_set_just_after_local_midnight_on_monday_is_in_force_from_that_week()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 00:30:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);

        $this->actingAs($owner)->put(route('goals.update', Muscle::LowerBack), ['weekly_minimum' => '2500']);

        $goal = $owner->goals()->sole();
        $this->assertSame(Muscle::LowerBack, $goal->muscle);
        $this->assertSame('2026-10-05', $goal->effective_week->toDateString());
    }

    public function test_removing_a_goal_that_is_not_set_stores_nothing()
    {
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->removed()->create(['muscle' => Muscle::Chest, 'effective_week' => '2026-09-14']);
        $this->travelTo('2026-09-30 12:00:00');

        $this->actingAs($owner)->delete(route('goals.destroy', Muscle::Chest));
        $this->delete(route('goals.destroy', Muscle::Biceps));

        $this->assertSame(1, $owner->goals()->count());
    }

    public function test_saving_the_goal_already_in_force_stores_nothing()
    {
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-14']);
        $this->travelTo('2026-09-30 12:00:00');

        $this->actingAs($owner)->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => '5000']);

        $this->assertSame(1, $owner->goals()->count());
    }

    public function test_setting_a_goal_twice_in_the_same_week_replaces_that_weeks_version()
    {
        $this->travelTo('2026-09-28 08:00:00');
        $owner = User::factory()->create();
        $this->actingAs($owner)->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => '5000']);
        $this->travelTo('2026-10-04 20:00:00');

        $this->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => '6000.50']);

        $goal = $owner->goals()->sole();
        $this->assertSame('6000.50', $goal->weekly_minimum);
        $this->assertSame('2026-09-28', $goal->effective_week->toDateString());
    }

    /**
     * A moment to look at the Stats hub, and the chest Goal in force then. Chest has 6,000 kg Volume every Week.
     *
     * @return array<string, array{0: string, 1: int|null, 2: bool|null}>
     */
    public static function momentsInHistory(): array
    {
        return [
            'before the first Goal' => ['2026-09-02 12:00:00', null, null],
            'the Week the Goal is set' => ['2026-09-07 12:00:00', 5000, true],
            'a Week later' => ['2026-09-16 12:00:00', 5000, true],
            'the Week it is raised' => ['2026-09-21 12:00:00', 8000, false],
            'the Week it is removed' => ['2026-09-30 12:00:00', null, null],
        ];
    }

    #[DataProvider('momentsInHistory')]
    public function test_each_week_is_judged_against_the_goal_in_force_then(string $moment, ?int $expectedGoal, ?bool $expectedMet)
    {
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '8000.00', 'effective_week' => '2026-09-21']);
        Goal::factory()->for($owner)->removed()->create(['muscle' => Muscle::Chest, 'effective_week' => '2026-09-28']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-07']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->create();
        foreach (['2026-09-01', '2026-09-08', '2026-09-15', '2026-09-22', '2026-09-29'] as $day) {
            $workout = Workout::factory()->for($owner)->finished()->create(['started_at' => "{$day} 10:00:00"]);
            WorkoutSet::factory()
                ->for(WorkoutExercise::factory()->for($workout)->for($benchPress), 'workoutExercise')
                ->state(['target_reps' => 10, 'target_weight' => '600.00'])
                ->done()
                ->create();
        }
        $this->travelTo($moment);

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('thisWeek.muscles', [['muscle' => 'chest', 'volume' => 6000, 'goal' => $expectedGoal, 'met' => $expectedMet]])
        );
    }

    public function test_removing_a_goal_takes_it_out_of_force_from_this_week()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-14']);

        $response = $this->actingAs($owner)->delete(route('goals.destroy', Muscle::Chest));

        $response
            ->assertRedirect(route('goals.index'))
            ->assertInertiaFlash('toast.message', 'Goal removed.');

        $this->assertSame(
            [['2026-09-14', '5000.00'], ['2026-09-28', null]],
            $owner->goals()->orderBy('effective_week')->get()->map(fn (Goal $goal) => [$goal->effective_week->toDateString(), $goal->weekly_minimum])->all(),
        );
        $this->get(route('statistics.hub'))->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles', []));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidWeeklyMinimums(): array
    {
        return [
            'missing' => [null, 'The weekly minimum field is required.'],
            'zero' => ['0', 'The weekly minimum field must be greater than 0.'],
            'negative' => ['-100', 'The weekly minimum field must be greater than 0.'],
            'three decimals' => ['100.125', 'The weekly minimum field must have 0-2 decimal places.'],
            'not a number' => ['lots', 'The weekly minimum field must be a number.'],
            'too large' => ['10000000', 'The weekly minimum field must not be greater than 9999999.99.'],
        ];
    }

    #[DataProvider('invalidWeeklyMinimums')]
    public function test_a_goal_needs_a_positive_weekly_minimum(mixed $weeklyMinimum, string $message)
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => $weeklyMinimum]);

        $response->assertSessionHasErrors(['weekly_minimum' => $message]);
        $this->assertDatabaseEmpty('goals');
    }

    public function test_a_goal_for_an_unknown_muscle_returns_404()
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->put('/goals/arms', ['weekly_minimum' => '5000']);

        $response->assertNotFound();
        $this->assertDatabaseEmpty('goals');
    }

    public function test_the_goals_page_lists_every_muscle_with_the_goal_in_force_this_week()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::LowerBack, 'weekly_minimum' => '2500.00', 'effective_week' => '2026-09-14']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '9000.00', 'effective_week' => '2026-10-05']);
        Goal::factory()->create(['muscle' => Muscle::Biceps, 'weekly_minimum' => '1000.00', 'effective_week' => '2026-09-14']);

        $response = $this->actingAs($owner)->get(route('goals.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('goals/index')
            ->has('goals', 17)
            ->where('goals.3', ['muscle' => 'biceps', 'weekly_minimum' => null])
            ->where('goals.5', ['muscle' => 'chest', 'weekly_minimum' => null])
            ->where('goals.10', ['muscle' => 'lower back', 'weekly_minimum' => 2500])
        );
    }

    public function test_setting_a_goal_leaves_another_users_goal_for_that_muscle_alone()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $theirs = Goal::factory()->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '1000.00', 'effective_week' => '2026-09-28']);
        $owner = User::factory()->create();

        $this->actingAs($owner)->put(route('goals.update', Muscle::Chest), ['weekly_minimum' => '5000']);

        $this->assertSame('1000.00', $theirs->refresh()->weekly_minimum);
        $this->assertSame('5000.00', $owner->goals()->sole()->weekly_minimum);
    }

    public function test_another_users_goals_do_not_show_on_the_stats_hub()
    {
        $this->travelTo('2026-09-30 12:00:00');
        Goal::factory()->create(['muscle' => Muscle::Chest, 'effective_week' => '2026-09-28']);
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get(route('statistics.hub'));

        $response->assertInertia(fn (Assert $page) => $page->where('thisWeek.muscles', []));
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('goals.index'))->assertRedirect(route('login'));
    }
}
