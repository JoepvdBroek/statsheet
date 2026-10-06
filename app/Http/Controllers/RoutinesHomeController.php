<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoutinesHomeController extends Controller
{
    /**
     * Show the Routines home: the Workout in progress to resume, a nudge to set a Bodyweight while it is empty,
     * the owner's Routines in use to start a Workout from, the one most likely due first, and the archived ones when asked.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workout = $user->workouts()->inProgress()->with('routine')->first();

        return Inertia::render('home', [
            'routines' => RoutineResource::collection($this->routines($user)->active()->dueFirst()->get())->resolve(),
            'archivedRoutines' => $request->boolean('archived')
                ? RoutineResource::collection($this->routines($user)->archived()->orderBy('name')->orderBy('id')->get())->resolve()
                : null,
            'workoutInProgress' => $workout === null ? null : [
                'id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
                'routine_name' => $workout->routine?->name,
            ],
            'bodyweightNudge' => $user->bodyweight === null,
        ]);
    }

    /**
     * The owner's Routines with their planned Exercises and Sets.
     *
     * @return Builder<Routine>
     */
    private function routines(User $user): Builder
    {
        return $user->routines()
            ->with('exercises.exercise.muscles', 'exercises.sets')
            ->getQuery();
    }
}
