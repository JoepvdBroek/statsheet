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

class MonthlySummaryTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_the_chosen_month_shows_its_volume_per_muscle_and_workout_count_in_progress_or_not_with_links_to_the_months_around_it()
    {
        $this->travelTo('2026-09-30 12:00:00');
        $owner = User::factory()->create();
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-07-31 10:00:00']), $squat, [$this->doneSet(10, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-08-03 10:00:00']), $benchPress, [$this->doneSet(10, '80.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-08-20 10:00:00']), $benchPress, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-08-27 10:00:00']), $squat, [$this->doneSet(10, '120.00')]);
        Workout::factory()->for($owner)->create(['started_at' => '2026-08-29 10:00:00']);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-01 10:00:00']), $squat, [$this->doneSet(10, '100.00')]);
        $theirBenchPress = Exercise::factory()->primaryMuscle(Muscle::Chest)->create();
        $this->perform(Workout::factory()->for($theirBenchPress->user)->finished()->create(['started_at' => '2026-08-12 10:00:00']), $theirBenchPress, [$this->doneSet(10, '50.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.monthly-summary', ['month' => '2026-08']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('statistics/monthly-summary')
            ->where('month', '2026-08')
            ->where('previousMonth', '2026-07')
            ->where('nextMonth', '2026-09')
            ->where('workouts', 4)
            ->where('volume', [
                ['muscle' => 'chest', 'volume' => 1300],
                ['muscle' => 'quadriceps', 'volume' => 1200],
                ['muscle' => 'triceps', 'volume' => 650],
            ])
        );
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: list<array{muscle: string, volume: int}>}>
     */
    public static function monthsAroundTheLastEvening(): array
    {
        return [
            'the month of that evening' => ['2026-09', 1, [['muscle' => 'quadriceps', 'volume' => 1000]]],
            'the month it already is in UTC' => ['2026-10', 0, []],
        ];
    }

    /**
     * @param  list<array{muscle: string, volume: int}>  $volume
     */
    #[DataProvider('monthsAroundTheLastEvening')]
    public function test_a_workout_on_the_last_evening_of_a_month_counts_in_that_month_in_the_owners_timezone(string $month, int $workouts, array $volume)
    {
        $this->travelTo('2026-10-15 12:00:00');
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $septemberThirtiethEveningInNewYork = '2026-10-01 01:30:00';
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => $septemberThirtiethEveningInNewYork]), $squat, [$this->doneSet(10, '100.00')]);

        $response = $this->actingAs($owner)->get(route('statistics.monthly-summary', ['month' => $month]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workouts', $workouts)
            ->where('volume', $volume)
        );
    }

    /**
     * @return array<string, array{0: string, 1: list<array{muscle: string, weeks_met: int, weeks_with_goal: int}>}>
     */
    public static function monthsWithGoals(): array
    {
        return [
            'August, whose last Week runs into September' => ['2026-08', [
                ['muscle' => 'chest', 'weeks_met' => 1, 'weeks_with_goal' => 5],
                ['muscle' => 'quadriceps', 'weeks_met' => 0, 'weeks_with_goal' => 5],
            ]],
            'September, with Goals changed mid-month and a last Week running into October' => ['2026-09', [
                ['muscle' => 'chest', 'weeks_met' => 3, 'weeks_with_goal' => 4],
                ['muscle' => 'lats', 'weeks_met' => 0, 'weeks_with_goal' => 3],
                ['muscle' => 'quadriceps', 'weeks_met' => 1, 'weeks_with_goal' => 1],
            ]],
            'October, which starts inside a September Week' => ['2026-10', [
                ['muscle' => 'chest', 'weeks_met' => 0, 'weeks_with_goal' => 4],
                ['muscle' => 'lats', 'weeks_met' => 0, 'weeks_with_goal' => 4],
            ]],
        ];
    }

    /**
     * @param  list<array{muscle: string, weeks_met: int, weeks_with_goal: int}>  $goals
     */
    #[DataProvider('monthsWithGoals')]
    public function test_each_goal_counts_the_weeks_it_was_met_whose_monday_falls_in_the_month_against_the_goal_in_force_then(string $month, array $goals)
    {
        $this->travelTo('2026-11-15 12:00:00');
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '1000.00', 'effective_week' => '2026-08-03']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '2000.00', 'effective_week' => '2026-09-21']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Quadriceps, 'weekly_minimum' => '800.00', 'effective_week' => '2026-08-03']);
        Goal::factory()->for($owner)->removed()->create(['muscle' => Muscle::Quadriceps, 'effective_week' => '2026-09-14']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Lats, 'weekly_minimum' => '500.00', 'effective_week' => '2026-09-14']);
        Goal::factory()->create(['muscle' => Muscle::Biceps, 'weekly_minimum' => '100.00', 'effective_week' => '2026-08-03']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->create();
        $squat = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-09 10:00:00']), $squat, [$this->doneSet(10, '100.00')]);
        $weekOfAugustThirtyFirst = ['2026-09-02'];
        $weekOfSeptemberTwentyEighth = ['2026-09-29', '2026-10-02'];
        foreach (['2026-09-08', '2026-09-15', '2026-09-22', ...$weekOfAugustThirtyFirst, ...$weekOfSeptemberTwentyEighth] as $day) {
            $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => "{$day} 10:00:00"]), $benchPress, [$this->doneSet(10, '100.00')]);
        }

        $response = $this->actingAs($owner)->get(route('statistics.monthly-summary', ['month' => $month]));

        $response->assertInertia(fn (Assert $page) => $page->where('goals', $goals));
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function unchosenMonths(): array
    {
        return [
            'no month chosen' => [[]],
            'a month that does not exist' => [['month' => '2026-13']],
            'not a month' => [['month' => 'september']],
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('unchosenMonths')]
    public function test_without_a_chosen_month_this_month_is_shown_with_its_weeks_up_to_this_one_in_the_owners_timezone_and_no_next_month(array $query)
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 00:30:00', 'Europe/Amsterdam'));
        $owner = User::factory()->create(['timezone' => 'Europe/Amsterdam']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '1000.00', 'effective_week' => '2026-09-07']);

        $response = $this->actingAs($owner)->get(route('statistics.monthly-summary', $query));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-10')
            ->where('previousMonth', '2026-09')
            ->where('nextMonth', null)
            ->where('goals', [['muscle' => 'chest', 'weeks_met' => 0, 'weeks_with_goal' => 3]])
        );
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('statistics.monthly-summary'))->assertRedirect(route('login'));
    }
}
