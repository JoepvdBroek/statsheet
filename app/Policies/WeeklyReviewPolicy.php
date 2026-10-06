<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Auth\Access\Response;

class WeeklyReviewPolicy
{
    /**
     * Determine whether the user can read the Weekly Review.
     */
    public function view(User $user, WeeklyReview $review): Response
    {
        return $this->owns($user, $review);
    }

    /**
     * Determine whether the user can rate the Weekly Review, or change or take back their rating. Only a review whose generation is done can be rated.
     */
    public function rate(User $user, WeeklyReview $review): Response
    {
        $ownership = $this->owns($user, $review);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $review->status === ReviewStatus::Done
            ? Response::allow()
            : Response::deny(__('A review can only be rated once it is done. Wait for it, or retry.'));
    }

    /**
     * Another user's review is answered as if it doesn't exist.
     */
    private function owns(User $user, WeeklyReview $review): Response
    {
        return $user->id === $review->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
