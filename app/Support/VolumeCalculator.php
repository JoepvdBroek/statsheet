<?php

namespace App\Support;

use App\Enums\MuscleRole;
use App\Models\User;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;

/**
 * Computes Volume per Muscle from the log when read, with no stored aggregates, so edits and deletions show up straight away.
 *
 * Volume is the tonnage (reps × weight) of done, non-warm-up Sets: in full to each primary Muscle and half to each secondary Muscle.
 * A Bodyweight Exercise's weight is the Workout's Bodyweight plus the added load, a missing Bodyweight counting as 0.
 * A Workout counts in the Week of its start, in progress or not.
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

        $rows = WorkoutSet::query()
            ->join('workout_exercises', 'workout_exercises.id', '=', 'workout_sets.workout_exercise_id')
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->join('exercises', 'exercises.id', '=', 'workout_exercises.exercise_id')
            ->join('exercise_muscles', 'exercise_muscles.exercise_id', '=', 'exercises.id')
            ->where('workouts.user_id', $user->id)
            ->where('workouts.started_at', '>=', $sortedWeeks->first()->utc())
            ->where('workouts.started_at', '<', $sortedWeeks->last()->addWeek()->utc())
            ->whereNotNull('workout_sets.actual_reps')
            ->where('workout_sets.is_warm_up', false)
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

        foreach ($rows as $row) {
            $week = $calendar->weekOf(CarbonImmutable::parse($row->started_at, 'UTC'))->toDateString();

            if (array_key_exists($week, $volume)) {
                $volume[$week][$row->muscle] = ($volume[$week][$row->muscle] ?? 0) + (float) $row->volume;
            }
        }

        return array_map(function (array $muscles) {
            $muscles = array_filter(array_map(fn (float $tonnage) => round($tonnage, 2), $muscles));
            arsort($muscles);

            return $muscles;
        }, $volume);
    }
}
