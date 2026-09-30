<?php

namespace App\Console\Commands;

use App\Actions\AskForWeeklyReview;
use App\Enums\ReviewStatus;
use App\Models\User;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:schedule-weekly-reviews')]
#[Description('Queue the Weekly Review of the Week just ended for each owner in the 06:00 hour of their Monday')]
class ScheduleWeeklyReviews extends Command
{
    /**
     * The hour on Monday, in the owner's timezone, in which the Week just ended gets its review.
     * The command runs hourly, so this hour comes once a Week in every timezone.
     */
    private const int REVIEW_HOUR = 6;

    /**
     * Execute the console command.
     */
    public function handle(AskForWeeklyReview $askForWeeklyReview): int
    {
        foreach (User::query()->lazyById() as $user) {
            $thisWeek = WeekCalendar::for($user)->currentWeek();
            $weekJustEnded = $thisWeek->subWeek();

            if (! $this->isReviewHour($thisWeek)) {
                continue;
            }

            if ($user->workouts()->startedBetween($weekJustEnded, $thisWeek)->doesntExist()) {
                continue;
            }

            if ($this->hasReviewWrittenAfterItEnded($user, $weekJustEnded)) {
                continue;
            }

            $askForWeeklyReview($user, $weekJustEnded);
        }

        return self::SUCCESS;
    }

    /**
     * Whether it is now the review hour on the Monday of this Week.
     */
    private function isReviewHour(CarbonImmutable $thisWeek): bool
    {
        $reviewHour = $thisWeek->setTime(self::REVIEW_HOUR, 0);

        return now()->greaterThanOrEqualTo($reviewHour) && now()->lessThan($reviewHour->addHour());
    }

    /**
     * Whether the Week already has a review written once it had ended. One written earlier misses the rest of the Week, so it is written afresh.
     */
    private function hasReviewWrittenAfterItEnded(User $user, CarbonImmutable $week): bool
    {
        return $user->weeklyReviews()
            ->whereDate('week', $week->toDateString())
            ->where('status', ReviewStatus::Done)
            ->where('generated_at', '>=', $week->addWeek()->utc())
            ->exists();
    }
}
