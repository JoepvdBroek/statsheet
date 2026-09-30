<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Auth\Access\Response;

class WeeklyReviewPolicy
{
    /**
     * Determine whether the user can read the Weekly Review. Another user's review is answered as if it doesn't exist.
     */
    public function view(User $user, WeeklyReview $review): Response
    {
        return $user->id === $review->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
