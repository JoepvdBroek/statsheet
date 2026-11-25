<?php

namespace App\Support;

use App\Enums\SetKind;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Computes Set Count per Muscle from the log when read, with no stored aggregates, so edits and deletions show up straight away.
 *
 * Set Count is the number of done Working Sets, whatever their reps and weight: 1 to each primary Muscle and 0.5 to each secondary Muscle.
 * Direct Sets are the part of it from Exercises where the Muscle is primary.
 * A Workout counts in the Week and calendar month of its start, in progress or not.
 *
 * @phpstan-import-type Attributed from MuscleAttribution
 *
 * @phpstan-type SetCount array{set_count: float, direct_sets: float}
 */
class SetCountCalculator
{
    private MuscleAttribution $sets;

    public function __construct()
    {
        $this->sets = new MuscleAttribution(
            array_values(array_filter(SetKind::cases(), fn (SetKind $kind) => $kind->countsTowardSetCount())),
            '1',
        );
    }

    /**
     * Set Count and Direct Sets per Muscle in one Week, highest Set Count first. Muscles without Set Count are left out.
     *
     * @param  CarbonImmutable  $week  A Week as WeekCalendar gives it
     * @return array<string, SetCount>
     */
    public function forWeek(User $user, CarbonImmutable $week): array
    {
        return $this->perWeek($user, [$week])[$week->toDateString()];
    }

    /**
     * Set Count and Direct Sets per Muscle in each of the given Weeks, keyed by the Week's Monday date and then by Muscle, highest Set Count first.
     * Muscles without Set Count are left out.
     *
     * @param  list<CarbonImmutable>  $weeks  Weeks as WeekCalendar gives them
     * @return array<string, array<string, SetCount>>
     */
    public function perWeek(User $user, array $weeks): array
    {
        return array_map($this->setCounts(...), $this->sets->perWeek($user, $weeks));
    }

    /**
     * Set Count and Direct Sets per Muscle in one calendar month, highest Set Count first. Muscles without Set Count are left out.
     *
     * @param  CarbonImmutable  $month  A calendar month as WeekCalendar gives it
     * @return array<string, SetCount>
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        return $this->setCounts($this->sets->between($user, $month, $month->addMonth()));
    }

    /**
     * Set Count and Direct Sets per Muscle, highest Set Count first.
     *
     * @param  array<string, Attributed>  $muscles
     * @return array<string, SetCount>
     */
    private function setCounts(array $muscles): array
    {
        $setCounts = array_map(fn (array $sets) => ['set_count' => $sets['total'], 'direct_sets' => $sets['direct']], $muscles);
        uasort($setCounts, fn (array $a, array $b) => $b['set_count'] <=> $a['set_count']);

        return $setCounts;
    }
}
