<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExercisePolicy
{
    /**
     * Determine whether the user can see the Exercise's progress and Personal Records.
     */
    public function view(User $user, Exercise $exercise): Response
    {
        return $this->owns($user, $exercise);
    }

    /**
     * Determine whether the user can edit the Exercise.
     */
    public function update(User $user, Exercise $exercise): Response
    {
        return $this->owns($user, $exercise);
    }

    /**
     * Determine whether the user can delete or archive the Exercise.
     */
    public function delete(User $user, Exercise $exercise): Response
    {
        return $this->owns($user, $exercise);
    }

    /**
     * Determine whether the user can restore the archived Exercise.
     */
    public function restore(User $user, Exercise $exercise): Response
    {
        return $this->owns($user, $exercise);
    }

    /**
     * Another user's Exercise is answered as if it doesn't exist.
     */
    private function owns(User $user, Exercise $exercise): Response
    {
        return $user->id === $exercise->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
