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
     * Show the dashboard: resume the Workout in progress or start one, this Week's Volume per Muscle, and a nudge to set a Bodyweight while it is empty.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $workout = $user->workouts()->inProgress()->first();
        $week = WeekCalendar::for($user)->weekOf(now());
        $volume = $volumeCalculator->forWeek($user, $week);

        return Inertia::render('dashboard', [
            'workoutInProgress' => $workout === null ? null : [
                'id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
            ],
            'thisWeek' => [
                'starts_on' => $week->toDateString(),
                'muscles' => collect($volume)
                    ->map(fn (float $tonnage, string $muscle) => ['muscle' => $muscle, 'volume' => $tonnage])
                    ->values()
                    ->all(),
            ],
            'bodyweightNudge' => $user->bodyweight === null,
        ]);
    }
}
