<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns moments into Weeks (Monday to Sunday) and calendar months in the owner's timezone.
 * Everything week-based uses it: Volume, Goals, statistics and the Weekly Review.
 */
class WeekCalendar
{
    public function __construct(private string $timezone) {}

    /**
     * The calendar of the owner's timezone.
     */
    public static function for(User $user): self
    {
        return new self($user->timezone);
    }

    /**
     * The Week a moment falls in: its Monday at midnight in the owner's timezone.
     */
    public function weekOf(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)
            ->setTimezone($this->timezone)
            ->startOfWeek(CarbonInterface::MONDAY);
    }

    /**
     * The Week it is now.
     */
    public function currentWeek(): CarbonImmutable
    {
        return $this->weekOf(now());
    }

    /**
     * The calendar month a moment falls in: its first day at midnight in the owner's timezone.
     */
    public function monthOf(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)
            ->setTimezone($this->timezone)
            ->startOfMonth();
    }

    /**
     * The Weeks from the one containing the first moment up to and including the one containing the second, oldest first.
     *
     * @return list<CarbonImmutable>
     */
    public function weeksBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = [];

        for ($week = $this->weekOf($from); $week->lessThanOrEqualTo($this->weekOf($to)); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
