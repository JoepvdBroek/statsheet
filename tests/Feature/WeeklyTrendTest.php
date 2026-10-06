<?php

namespace Tests\Feature;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\Goal;
use App\Models\User;
use App\Models\Workout;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WeeklyTrendTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_the_chosen_muscle_gets_one_point_per_week_with_weeks_without_workouts_at_zero()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-15 10:00:00']), $benchPress, [$this->doneSet(10, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => '2026-09-30 10:00:00']), $benchPress, [$this->doneSet(5, '100.00')]);
        $theirDips = Exercise::factory()->primaryMuscle(Muscle::Triceps)->create();
        $this->perform(Workout::factory()->for($theirDips->user)->finished()->create(['started_at' => '2026-09-22 10:00:00']), $theirDips, [$this->doneSet(10, '50.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.weekly-trend', ['muscle' => 'triceps']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('statistics/weekly-trend')
            ->where('muscle', 'triceps')
            ->has('muscles', 17)
            ->has('weeks', 12)
            ->where('weeks.0', ['starts_on' => '2026-07-13', 'volume' => 0, 'goal' => null])
            ->where('weeks.9', ['starts_on' => '2026-09-14', 'volume' => 500, 'goal' => null])
            ->where('weeks.10', ['starts_on' => '2026-09-21', 'volume' => 0, 'goal' => null])
            ->where('weeks.11', ['starts_on' => '2026-09-28', 'volume' => 250, 'goal' => null])
        );
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function unchosenMuscles(): array
    {
        return [
            'no Muscle chosen' => [[]],
            'an unknown Muscle' => [['muscle' => 'arms']],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('unchosenMuscles')]
    public function test_without_a_chosen_muscle_the_one_with_the_most_volume_in_the_range_is_shown(array $query)
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Triceps)->withSecondaryMuscle(Muscle::Chest)->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-05-05 10:00:00']), $squat, [$this->doneSet(10, '500.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-08-04 10:00:00']), $squat, [$this->doneSet(10, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-15 10:00:00']), $benchPress, [$this->doneSet(10, '80.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-22 10:00:00']), $benchPress, [$this->doneSet(10, '80.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.weekly-trend', $query));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('muscle', 'triceps')
            ->where('weeks.9.volume', 800)
        );
    }

    public function test_without_any_volume_in_the_range_the_first_muscle_is_shown()
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->get(route('statistics.weekly-trend'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('muscle', 'abdominals')
            ->has('weeks', 12)
        );
    }

    public function test_each_week_shows_the_goal_in_force_then_as_it_changes_inside_the_range()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '4000.00', 'effective_week' => '2026-06-01']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.50', 'effective_week' => '2026-08-10']);
        Goal::factory()->for($owner)->removed()->create(['muscle' => Muscle::Chest, 'effective_week' => '2026-09-14']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '6000.00', 'effective_week' => '2026-09-28']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Lats, 'weekly_minimum' => '9000.00', 'effective_week' => '2026-08-17']);
        Goal::factory()->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '1000.00', 'effective_week' => '2026-08-24']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->create();
        foreach (['2026-08-06', '2026-08-13', '2026-09-29'] as $day) {
            $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => "{$day} 10:00:00"]), $benchPress, [$this->doneSet(10, '500.00')]);
        }

        $response = $this->actingAs($owner)->get(route('statistics.weekly-trend', ['muscle' => 'chest']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('weeks', [
                ['starts_on' => '2026-07-13', 'volume' => 0, 'goal' => 4000],
                ['starts_on' => '2026-07-20', 'volume' => 0, 'goal' => 4000],
                ['starts_on' => '2026-07-27', 'volume' => 0, 'goal' => 4000],
                ['starts_on' => '2026-08-03', 'volume' => 5000, 'goal' => 4000],
                ['starts_on' => '2026-08-10', 'volume' => 5000, 'goal' => 5000.5],
                ['starts_on' => '2026-08-17', 'volume' => 0, 'goal' => 5000.5],
                ['starts_on' => '2026-08-24', 'volume' => 0, 'goal' => 5000.5],
                ['starts_on' => '2026-08-31', 'volume' => 0, 'goal' => 5000.5],
                ['starts_on' => '2026-09-07', 'volume' => 0, 'goal' => 5000.5],
                ['starts_on' => '2026-09-14', 'volume' => 0, 'goal' => null],
                ['starts_on' => '2026-09-21', 'volume' => 0, 'goal' => null],
                ['starts_on' => '2026-09-28', 'volume' => 5000, 'goal' => 6000],
            ])
        );
    }

    public function test_the_weeks_follow_the_owners_timezone()
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-27 23:45:00', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $deadlift = Exercise::factory()->for($owner)->primaryMuscle(Muscle::LowerBack)->create();
        $sundayNightInNewYork = '2026-09-28 03:30:00';
        $this->perform(Workout::factory()->for($owner)->create(['started_at' => $sundayNightInNewYork]), $deadlift, [$this->doneSet(5, '200.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.weekly-trend', ['muscle' => 'lower back']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('weeks.0.starts_on', '2026-07-06')
            ->where('weeks.11', ['starts_on' => '2026-09-21', 'volume' => 1000, 'goal' => null])
        );
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('statistics.weekly-trend'))->assertRedirect(route('login'));
    }
}
