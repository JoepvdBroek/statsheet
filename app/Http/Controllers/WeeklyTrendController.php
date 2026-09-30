<?php

namespace App\Http\Controllers;

use App\Enums\Muscle;
use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyTrendController extends Controller
{
    /**
     * How many Weeks the trend shows, this Week included.
     */
    private const WEEKS = 12;

    /**
     * Show one Muscle's Volume per Week over recent Weeks, with the Goal in force in each.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $calendar = WeekCalendar::for($user);
        $thisWeek = $calendar->currentWeek();
        $weeks = $calendar->weeksBetween($thisWeek->subWeeks(self::WEEKS - 1), $thisWeek);
        $volume = $volumeCalculator->perWeek($user, $weeks);
        $goals = $user->goalsInForcePerWeek($weeks);
        $muscle = $request->enum('muscle', Muscle::class) ?? $this->withMostVolume($volume);

        return Inertia::render('statistics/weekly-trend', [
            'muscle' => $muscle->value,
            'muscles' => array_column(Muscle::cases(), 'value'),
            'weeks' => array_map(function (CarbonImmutable $week) use ($volume, $goals, $muscle) {
                $monday = $week->toDateString();

                return [
                    'starts_on' => $monday,
                    'volume' => $volume[$monday][$muscle->value] ?? 0,
                    'goal' => $goals[$monday][$muscle->value] ?? null,
                ];
            }, $weeks),
        ]);
    }

    /**
     * The Muscle with the most Volume across the Weeks, or the first Muscle when none has any.
     *
     * @param  array<string, array<string, float>>  $volume  Volume per Muscle per Week, as VolumeCalculator gives it
     */
    private function withMostVolume(array $volume): Muscle
    {
        $totals = [];

        foreach ($volume as $muscles) {
            foreach ($muscles as $muscle => $tonnage) {
                $totals[$muscle] = ($totals[$muscle] ?? 0) + $tonnage;
            }
        }

        arsort($totals);

        return Muscle::tryFrom((string) array_key_first($totals)) ?? Muscle::cases()[0];
    }
}
