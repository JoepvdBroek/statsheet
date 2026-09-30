<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Auth\Access\Response;

class WorkoutPolicy
{
    /**
     * Determine whether the user can open the Workout.
     */
    public function view(User $user, Workout $workout): Response
    {
        return $this->owns($user, $workout);
    }

    /**
     * Determine whether the user can log, change or finish the Workout.
     */
    public function update(User $user, Workout $workout): Response
    {
        return $this->owns($user, $workout);
    }

    /**
     * Another user's Workout is answered as if it doesn't exist.
     */
    private function owns(User $user, Workout $workout): Response
    {
        return $user->id === $workout->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
