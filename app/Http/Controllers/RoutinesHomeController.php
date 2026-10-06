<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Models\User;
use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoutinesHomeController extends Controller
{
    /**
     * Show the Routines home: the Workout in progress to resume, a nudge to set a Bodyweight while it is empty,
     * the owner's Routines in use to start a Workout from, and the archived ones when asked.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $workout = $user->workouts()->inProgress()->with('routine')->first();

        return Inertia::render('home', [
            'routines' => RoutineResource::collection($this->routines($user)->active()->get())->resolve(),
            'archivedRoutines' => $request->boolean('archived')
                ? RoutineResource::collection($this->routines($user)->archived()->get())->resolve()
                : null,
            'workoutInProgress' => $workout === null ? null : [
                'id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
                'routine_name' => $workout->routine?->name,
            ],
            'bodyweightNudge' => $user->bodyweight === null,
            'thisWeek' => $this->thisWeek($user, $volumeCalculator),
        ]);
    }

    /**
     * The owner's Routines with their planned Exercises and Sets, by name.
     *
     * @return Builder<Routine>
     */
    private function routines(User $user): Builder
    {
        return $user->routines()
            ->with('exercises.exercise.muscles', 'exercises.sets')
            ->orderBy('name')
            ->orderBy('id')
            ->getQuery();
    }

    /**
     * The Workouts started this Week and its total Volume, with last Week's total Volume up to the same moment
     * (from its Monday up to now minus 7 days), so that the two compare fairly on any day of the Week.
     *
     * @return array{workouts: int, volume: float, last_week_volume: float}
     */
    private function thisWeek(User $user, VolumeCalculator $volumeCalculator): array
    {
        $week = WeekCalendar::for($user)->currentWeek();
        $now = CarbonImmutable::now($user->timezone);

        return [
            'workouts' => $user->workouts()->startedBetween($week, $week->addWeek())->count(),
            'volume' => round(array_sum($volumeCalculator->between($user, $week, $week->addWeek())), 2),
            'last_week_volume' => round(array_sum($volumeCalculator->between($user, $week->subWeek(), $now->subWeek())), 2),
        ];
    }
}
