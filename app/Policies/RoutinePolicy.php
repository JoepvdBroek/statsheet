<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RoutinePolicy
{
    /**
     * Determine whether the user can edit the Routine.
     */
    public function update(User $user, Routine $routine): Response
    {
        return $this->owns($user, $routine);
    }

    /**
     * Determine whether the user can archive the Routine.
     */
    public function archive(User $user, Routine $routine): Response
    {
        return $this->owns($user, $routine);
    }

    /**
     * Determine whether the user can restore the archived Routine.
     */
    public function restore(User $user, Routine $routine): Response
    {
        return $this->owns($user, $routine);
    }

    /**
     * Determine whether the user can start a Workout from the Routine. Archived Routines can't be started.
     */
    public function start(User $user, Routine $routine): Response
    {
        $ownership = $this->owns($user, $routine);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $routine->archived_at === null
            ? Response::allow()
            : Response::deny(__('Archived Routines can\'t be started.'));
    }

    /**
     * Another user's Routine is answered as if it doesn't exist.
     */
    private function owns(User $user, Routine $routine): Response
    {
        return $user->id === $routine->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
