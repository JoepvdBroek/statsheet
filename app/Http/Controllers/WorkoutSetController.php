<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkWorkoutSetDoneRequest;
use App\Http\Requests\RepositionRequest;
use App\Http\Requests\UpdateWorkoutSetRequest;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class WorkoutSetController extends Controller
{
    /**
     * Add a Set, without a Target, to the end of an Exercise in the Workout.
     */
    #[Authorize('update', 'workout')]
    public function store(Workout $workout, WorkoutExercise $exercise): RedirectResponse
    {
        $exercise->addSet();

        return to_route('workouts.show', $workout);
    }

    /**
     * Correct a Set's Actual, or mark or unmark it as a Warm-up Set.
     */
    #[Authorize('update', 'workout')]
    public function update(UpdateWorkoutSetRequest $request, Workout $workout, WorkoutSet $set): RedirectResponse
    {
        if ($request->has('is_warm_up')) {
            $set->is_warm_up = $request->boolean('is_warm_up');
        }

        if ($actual = $request->actual()) {
            $set->actual_reps = $actual['reps'];
            $set->actual_weight = $actual['weight'];
        }

        $set->save();

        return to_route('workouts.show', $workout);
    }

    /**
     * Mark a Set done by logging its Actual. What wasn't typed comes from the Target.
     */
    #[Authorize('update', 'workout')]
    public function markDone(MarkWorkoutSetDoneRequest $request, Workout $workout, WorkoutSet $set): RedirectResponse
    {
        $actual = $request->actual();

        $set->logActual($actual['reps'], $actual['weight']);

        return to_route('workouts.show', $workout);
    }

    /**
     * Mark a Set not done, which clears its Actual.
     */
    #[Authorize('update', 'workout')]
    public function markNotDone(Workout $workout, WorkoutSet $set): RedirectResponse
    {
        $set->clearActual();

        return to_route('workouts.show', $workout);
    }

    /**
     * Move a Set to another place among its Exercise's Sets.
     */
    #[Authorize('update', 'workout')]
    public function move(RepositionRequest $request, Workout $workout, WorkoutSet $set): RedirectResponse
    {
        $set->workoutExercise->moveSet($set, $request->position());

        return to_route('workouts.show', $workout);
    }

    /**
     * Remove a Set from the Workout.
     */
    #[Authorize('update', 'workout')]
    public function destroy(Workout $workout, WorkoutSet $set): RedirectResponse
    {
        $set->delete();

        return to_route('workouts.show', $workout);
    }
}
