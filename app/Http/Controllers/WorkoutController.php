<?php

namespace App\Http\Controllers;

use App\Actions\StartWorkout;
use App\Http\Requests\UpdateWorkoutRequest;
use App\Http\Resources\ExerciseResource;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    /**
     * Start an empty Workout, or go to the one already in progress.
     */
    public function store(Request $request, StartWorkout $startWorkout): RedirectResponse
    {
        $workout = $startWorkout->empty($request->user());

        if (! $workout->wasRecentlyCreated) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('You already have a Workout in progress.')]);
        }

        return to_route('workouts.show', $workout);
    }

    /**
     * Show the logging screen for a Workout.
     */
    #[Authorize('view', 'workout')]
    public function show(Request $request, Workout $workout): Response
    {
        return Inertia::render('workouts/show', [
            'workout' => WorkoutResource::make($workout->load('exercises.exercise.muscles', 'exercises.sets'))->resolve(),
            'exercises' => Inertia::defer(fn () => ExerciseResource::collection(
                $request->user()->exercises()->active()->with('muscles')->orderBy('name')->orderBy('id')->get(),
            )->resolve()),
        ]);
    }

    /**
     * Save the Workout Note.
     */
    #[Authorize('update', 'workout')]
    public function update(UpdateWorkoutRequest $request, Workout $workout): RedirectResponse
    {
        $workout->update(['note' => $request->validated('note')]);

        return to_route('workouts.show', $workout);
    }

    /**
     * Finish the Workout, which moves it from in progress to finished.
     */
    #[Authorize('update', 'workout')]
    public function finish(Workout $workout): RedirectResponse
    {
        $workout->finish();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workout finished.')]);

        return to_route('dashboard');
    }
}
