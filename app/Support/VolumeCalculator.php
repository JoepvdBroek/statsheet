<?php

namespace App\Support;

use App\Enums\MuscleRole;
use App\Enums\SetKind;
use App\Models\User;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Computes Volume per Muscle from the log when read, with no stored aggregates, so edits and deletions show up straight away.
 *
 * Volume is the tonnage (reps × weight) of done, non-warm-up Sets: in full to each primary Muscle and half to each secondary Muscle.
 * A Bodyweight Exercise's weight is the Workout's Bodyweight plus the added load, a missing Bodyweight counting as 0.
 * A Workout counts in the Week and calendar month of its start, in progress or not.
 */
class VolumeCalculator
{
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
        $calendar = WeekCalendar::for($user);
        $volume = collect($weeks)->mapWithKeys(fn (CarbonImmutable $week) => [$week->toDateString() => []])->all();

        if ($volume === []) {
            return [];
        }

        $sortedWeeks = collect($weeks)->sort()->values();
        $rows = $this->perWorkout($user, $sortedWeeks->first(), $sortedWeeks->last()->addWeek());

        foreach ($rows as $row) {
            $week = $calendar->weekOf(CarbonImmutable::parse($row->started_at, 'UTC'))->toDateString();

            if (array_key_exists($week, $volume)) {
                $volume[$week][$row->muscle] = ($volume[$week][$row->muscle] ?? 0) + (float) $row->volume;
            }
        }

        return array_map($this->rounded(...), $volume);
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
        $volume = [];

        foreach ($this->perWorkout($user, $from, $until) as $row) {
            $volume[$row->muscle] = ($volume[$row->muscle] ?? 0) + (float) $row->volume;
        }

        return $this->rounded($volume);
    }

    /**
     * Volume per Muscle of each Workout started from the first moment up to the second: one row per Workout and Muscle with its started_at, muscle and volume.
     *
     * @return Collection<int, stdClass>
     */
    private function perWorkout(User $user, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return WorkoutSet::query()
            ->join('workout_exercises', 'workout_exercises.id', '=', 'workout_sets.workout_exercise_id')
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->join('exercises', 'exercises.id', '=', 'workout_exercises.exercise_id')
            ->join('exercise_muscles', 'exercise_muscles.exercise_id', '=', 'exercises.id')
            ->where('workouts.user_id', $user->id)
            ->where('workouts.started_at', '>=', $from->utc())
            ->where('workouts.started_at', '<', $until->utc())
            ->whereNotNull('workout_sets.actual_reps')
            ->where('workout_sets.kind', '!=', SetKind::WarmUp->value)
            ->select('workouts.started_at', 'exercise_muscles.muscle')
            ->selectRaw(
                'SUM(workout_sets.actual_reps'
                .' * (workout_sets.actual_weight + CASE WHEN exercises.is_bodyweight THEN COALESCE(workouts.bodyweight, 0) ELSE 0 END)'
                .' * CASE WHEN exercise_muscles.role = ? THEN 1 ELSE 0.5 END) AS volume',
                [MuscleRole::Primary->value],
            )
            ->groupBy('workouts.id', 'workouts.started_at', 'exercise_muscles.muscle')
            ->orderBy('exercise_muscles.muscle')
            ->toBase()
            ->get();
    }

    /**
     * Volume per Muscle rounded to the gram, Muscles without Volume left out, most Volume first.
     *
     * @param  array<string, float>  $muscles
     * @return array<string, float>
     */
    private function rounded(array $muscles): array
    {
        $muscles = array_filter(array_map(fn (float $tonnage) => round($tonnage, 2), $muscles));
        arsort($muscles);

        return $muscles;
    }
}
