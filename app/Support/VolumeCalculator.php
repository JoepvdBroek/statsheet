<?php

namespace App\Support;

use App\Enums\SetKind;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Computes Volume per Muscle from the log when read, with no stored aggregates, so edits and deletions show up straight away.
 *
 * Volume is the tonnage (reps × weight) of done, non-warm-up Sets: in full to each primary Muscle and half to each secondary Muscle.
 * A Bodyweight Exercise's weight is the Workout's Bodyweight plus the added load, a missing Bodyweight counting as 0.
 * A Workout counts in the Week and calendar month of its start, in progress or not.
 *
 * @phpstan-import-type Attributed from MuscleAttribution
 */
class VolumeCalculator
{
    private MuscleAttribution $tonnage;

    public function __construct()
    {
        $this->tonnage = new MuscleAttribution(
            array_values(array_filter(SetKind::cases(), fn (SetKind $kind) => $kind->counts())),
            'workout_sets.actual_reps'
            .' * (workout_sets.actual_weight + CASE WHEN exercises.is_bodyweight THEN COALESCE(workouts.bodyweight, 0) ELSE 0 END)',
        );
    }

    /**
     * Volume per Muscle in one Week, most Volume first. Muscles without Volume are left out.
     *
     * @param  CarbonImmutable  $week  A Week as WeekCalendar gives it
     * @return array<string, float>
     */
    public function forWeek(User $user, CarbonImmutable $week): array
    {
        return $this->perWeek($user, [$week])[$week->toDateString()];
    }

    /**
     * Volume per Muscle in each of the given Weeks, keyed by the Week's Monday date and then by Muscle, most Volume first.
     * Muscles without Volume are left out.
     *
     * @param  list<CarbonImmutable>  $weeks  Weeks as WeekCalendar gives them
     * @return array<string, array<string, float>>
     */
    public function perWeek(User $user, array $weeks): array
    {
        return array_map($this->volume(...), $this->tonnage->perWeek($user, $weeks));
    }

    /**
     * Volume per Muscle in one calendar month, most Volume first. Muscles without Volume are left out.
     *
     * @param  CarbonImmutable  $month  A calendar month as WeekCalendar gives it
     * @return array<string, float>
     */
    public function forMonth(User $user, CarbonImmutable $month): array
    {
        return $this->between($user, $month, $month->addMonth());
    }

    /**
     * Volume per Muscle of the Workouts started from the first moment up to the second, most Volume first. Muscles without Volume are left out.
     *
     * @return array<string, float>
     */
    public function between(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->volume($this->tonnage->between($user, $from, $until));
    }

    /**
     * Volume per Muscle rounded to the gram, Muscles without Volume left out, most Volume first.
     *
     * @param  array<string, Attributed>  $muscles
     * @return array<string, float>
     */
    private function volume(array $muscles): array
    {
        $volume = array_filter(array_map(fn (array $tonnage) => round($tonnage['total'], 2), $muscles));
        arsort($volume);

        return $volume;
    }
}
