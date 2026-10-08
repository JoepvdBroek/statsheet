<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddWorkoutExerciseRequest;
use App\Http\Requests\RepositionRequest;
use App\Http\Requests\SwapWorkoutExerciseRequest;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class WorkoutExerciseController extends Controller
{
    /**
     * Add one of the owner's Exercises in use to the end of the Workout, with one Set.
     */
    #[Authorize('update', 'workout')]
    public function store(AddWorkoutExerciseRequest $request, Workout $workout): RedirectResponse
    {
        $workout->addExercise($request->exercise());

        return to_route('workouts.show', $workout);
    }

    /**
     * Move an Exercise to another place in the Workout's order.
     */
    #[Authorize('update', 'workout')]
    public function move(RepositionRequest $request, Workout $workout, WorkoutExercise $exercise): RedirectResponse
    {
        $workout->moveExercise($exercise, $request->position());

        return to_route('workouts.show', $workout);
    }

    /**
     * Swap another of the owner's Exercises in use into an Exercise's place, keeping its set count and kinds as the plan.
     */
    #[Authorize('update', 'workout')]
    public function swap(SwapWorkoutExerciseRequest $request, Workout $workout, WorkoutExercise $exercise): RedirectResponse
    {
        $exercise->swapFor($request->exercise());

        return to_route('workouts.show', $workout);
    }

    /**
     * Remove an Exercise and its Sets from the Workout.
     */
    #[Authorize('update', 'workout')]
    public function destroy(Workout $workout, WorkoutExercise $exercise): RedirectResponse
    {
        $exercise->delete();

        return to_route('workouts.show', $workout);
    }
}
