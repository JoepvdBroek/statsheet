<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;

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
}
