<?php

namespace App\Actions;

use App\Models\Routine;
use App\Models\RoutineSet;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Facades\DB;

/**
 * The only way a Workout is created. An owner has at most one Workout in progress, so starting while one is open returns that one instead.
 */
class StartWorkout
{
    /**
     * Start an empty Workout, without Exercises, snapshotting the owner's current Bodyweight.
     *
     * The returned Workout's `wasRecentlyCreated` tells whether it is new or the one already in progress.
     */
    public function empty(User $user): Workout
    {
        return $user->workouts()->inProgress()->first()
            ?? $user->workouts()->create([
                'started_at' => now(),
                'bodyweight' => $user->bodyweight,
            ]);
    }

    /**
     * Start a Workout from a Routine, snapshotting the owner's current Bodyweight and the Routine's Exercises and set counts (ADR 0001).
     * Each Set is Pre-filled from the same Set in the most recent earlier Workout containing its Exercise.
     *
     * The returned Workout's `wasRecentlyCreated` tells whether it is new or the one already in progress.
     */
    public function fromRoutine(User $user, Routine $routine): Workout
    {
        return $user->workouts()->inProgress()->first()
            ?? DB::transaction(function () use ($user, $routine) {
                $workout = $user->workouts()->create([
                    'routine_id' => $routine->id,
                    'started_at' => now(),
                    'bodyweight' => $user->bodyweight,
                ]);

                foreach ($routine->exercises()->with('sets')->get() as $position => $planned) {
                    $performed = $workout->exercises()->create([
                        'exercise_id' => $planned->exercise_id,
                        'position' => $position,
                    ]);

                    $performed->preFill($planned->sets
                        ->map(fn (RoutineSet $set) => [
                            'target_reps' => $set->target_reps,
                            'target_weight' => $set->target_weight,
                            'kind' => $set->kind,
                        ])
                        ->all());
                }

                return $workout;
            });
    }
}
