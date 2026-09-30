<?php

namespace App\Actions;

use App\Enums\ReviewStatus;
use App\Jobs\GenerateWeeklyReview;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;

/**
 * Asks for a Week's Weekly Review, or for a fresh one, generated in the background.
 * An existing review keeps its content until the new one is in. Asking again while it is being generated changes nothing.
 */
class AskForWeeklyReview
{
    /**
     * Mark the Week's review pending and queue its generation.
     *
     * @param  CarbonImmutable  $week  A Week as WeekCalendar gives it
     */
    public function __invoke(User $user, CarbonImmutable $week): WeeklyReview
    {
        $review = $user->weeklyReviews()->whereDate('week', $week->toDateString())->first()
            ?? $user->weeklyReviews()->make(['week' => $week->toDateString()]);

        if ($review->exists && $review->status === ReviewStatus::Pending) {
            return $review;
        }

        $review->status = ReviewStatus::Pending;
        $review->save();

        GenerateWeeklyReview::dispatch($review);

        return $review;
    }
}
