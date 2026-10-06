<?php

namespace App\Http\Controllers;

use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthlySummaryController extends Controller
{
    /**
     * Summarise a calendar month: its Volume per Muscle, its number of Workouts, and for each Goal the Weeks it was met.
     * Goals are judged on the Weeks whose Monday falls in the month, up to this Week, each against the Goal in force then.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $calendar = WeekCalendar::for($user);
        $thisMonth = $calendar->monthOf(now());
        $month = $this->chosenMonth($request->string('month')->value(), $user->timezone) ?? $thisMonth;
        $volume = $volumeCalculator->forMonth($user, $month);
        $weeks = array_values(array_filter(
            $calendar->weeksOfMonth($month),
            fn (CarbonImmutable $week) => $week->lessThanOrEqualTo($calendar->currentWeek()),
        ));

        return Inertia::render('statistics/monthly-summary', [
            'month' => $month->format('Y-m'),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->lessThan($thisMonth) ? $month->addMonth()->format('Y-m') : null,
            'workouts' => $user->workouts()->startedBetween($month, $month->addMonth())->count(),
            'volume' => array_map(fn (string $muscle) => [
                'muscle' => $muscle,
                'volume' => $volume[$muscle],
            ], array_keys($volume)),
            'goals' => $this->weeksMetPerGoal($volumeCalculator->perWeek($user, $weeks), $user->goalsInForcePerWeek($weeks)),
        ]);
    }

    /**
     * Each Muscle with a Goal in force in any of the Weeks: in how many of them the Goal was met, and in how many one was in force. By Muscle name.
     *
     * @param  array<string, array<string, float>>  $volume  Volume per Muscle per Week, as VolumeCalculator gives it
     * @param  array<string, array<string, float>>  $goals  The Goals in force per Week, as User::goalsInForcePerWeek gives them
     * @return list<array{muscle: string, weeks_met: int, weeks_with_goal: int}>
     */
    private function weeksMetPerGoal(array $volume, array $goals): array
    {
        $tally = [];

        foreach ($goals as $monday => $weeklyMinimums) {
            foreach ($weeklyMinimums as $muscle => $weeklyMinimum) {
                $tally[$muscle] ??= ['muscle' => $muscle, 'weeks_met' => 0, 'weeks_with_goal' => 0];
                $tally[$muscle]['weeks_with_goal']++;

                if (($volume[$monday][$muscle] ?? 0) >= $weeklyMinimum) {
                    $tally[$muscle]['weeks_met']++;
                }
            }
        }

        ksort($tally);

        return array_values($tally);
    }

    /**
     * The calendar month a "2026-09" value names, starting at midnight in the owner's timezone; empty when it names none.
     */
    private function chosenMonth(string $value, string $timezone): ?CarbonImmutable
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m')) {
            return null;
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $value, $timezone);

        return $month?->format('Y-m') === $value ? $month : null;
    }
}
