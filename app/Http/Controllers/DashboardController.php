<?php

namespace App\Http\Controllers;

use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard: resume the Workout in progress or start one, this Week's Volume per Muscle against its Goal, and a nudge to set a Bodyweight while it is empty.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $workout = $user->workouts()->inProgress()->first();
        $week = WeekCalendar::for($user)->currentWeek();
        $volume = $volumeCalculator->forWeek($user, $week);

        return Inertia::render('dashboard', [
            'workoutInProgress' => $workout === null ? null : [
                'id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
            ],
            'thisWeek' => [
                'starts_on' => $week->toDateString(),
                'muscles' => $this->againstGoals($volume, $user->goalsInForce($week)),
            ],
            'bodyweightNudge' => $user->bodyweight === null,
        ]);
    }

    /**
     * Each Muscle with Volume or a Goal in force: its Volume, its Goal, and whether the Goal is met. Most Volume first, then Muscles with a Goal but no Volume by name.
     *
     * @param  array<string, float>  $volume
     * @param  array<string, float>  $goals
     * @return list<array{muscle: string, volume: float, goal: float|null, met: bool|null}>
     */
    private function againstGoals(array $volume, array $goals): array
    {
        $goalsWithoutVolume = array_map(fn () => 0.0, array_diff_key($goals, $volume));
        ksort($goalsWithoutVolume);
        $muscles = $volume + $goalsWithoutVolume;

        return array_map(fn (string $muscle) => [
            'muscle' => $muscle,
            'volume' => $muscles[$muscle],
            'goal' => $goals[$muscle] ?? null,
            'met' => isset($goals[$muscle]) ? $muscles[$muscle] >= $goals[$muscle] : null,
        ], array_keys($muscles));
    }
}
