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
     * Determine whether the user can delete the Workout.
     */
    public function delete(User $user, Workout $workout): Response
    {
        return $this->owns($user, $workout);
    }

    /**
     * Determine whether the user can replace the Exercises and Sets of the Workout's Routine with the Workout's. Only a Workout started from a Routine has one.
     */
    public function updateRoutine(User $user, Workout $workout): Response
    {
        $ownership = $this->owns($user, $workout);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $workout->routine_id !== null
            ? Response::allow()
            : Response::deny(__('This Workout wasn\'t started from a Routine.'));
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
