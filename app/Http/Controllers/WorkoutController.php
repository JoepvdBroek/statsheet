<?php

namespace App\Http\Controllers;

use App\Actions\StartWorkout;
use App\Http\Requests\UpdateWorkoutRequest;
use App\Http\Resources\ExerciseResource;
use App\Http\Resources\WorkoutResource;
use App\Http\Resources\WorkoutSummaryResource;
use App\Models\Workout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    /**
     * List the owner's Workouts, newest first.
     */
    public function index(Request $request): Response
    {
        $workouts = $request->user()->workouts()
            ->with('routine')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(30);

        return Inertia::render('workouts/index', [
            'workouts' => Inertia::scroll(WorkoutSummaryResource::collection($workouts)),
        ]);
    }

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
            'workout' => WorkoutResource::make($workout->load('routine', 'exercises.exercise.muscles', 'exercises.sets'))->resolve(),
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

        return to_route('home');
    }

    /**
     * Replace the Routine's Exercises, order and Sets with this Workout's, only when the owner asks (ADR 0001).
     */
    #[Authorize('updateRoutine', 'workout')]
    public function updateRoutine(Workout $workout): RedirectResponse
    {
        DB::transaction(fn () => $workout->routine->syncExercises($workout->plannedExercises()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routine updated from this Workout.')]);

        return to_route('workouts.show', $workout);
    }

    /**
     * Delete the Workout with its Exercises and Sets, so it no longer counts anywhere.
     */
    #[Authorize('delete', 'workout')]
    public function destroy(Workout $workout): RedirectResponse
    {
        $workout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workout deleted.')]);

        return to_route('workouts.index');
    }
}
