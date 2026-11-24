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
 * Adds up an amount per done Set over the Muscles it is attributed to: in full to each primary Muscle and half to each secondary Muscle.
 * Volume and Set Count are both such amounts. It is computed from the log when read.
 *
 * A Workout counts in the Week and calendar month of its start, in progress or not.
 *
 * A Muscle's total is everything attributed to it; direct is the part of it from Exercises where the Muscle is primary.
 *
 * @phpstan-type Attributed array{total: float, direct: float}
 */
class MuscleAttribution
{
    /**
     * @param  list<SetKind>  $kinds  The kinds of Set that count
     * @param  literal-string  $amountPerSet  SQL of the amount one Set adds, over workout_sets, workouts and exercises
     */
    public function __construct(private array $kinds, private string $amountPerSet) {}

    /**
     * What each Muscle got in each of the given Weeks, keyed by the Week's Monday date and then by Muscle, by name. Muscles without counting Sets are left out.
     *
     * @param  list<CarbonImmutable>  $weeks  Weeks as WeekCalendar gives them
     * @return array<string, array<string, Attributed>>
     */
    public function perWeek(User $user, array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }

        $calendar = WeekCalendar::for($user);
        $sortedWeeks = collect($weeks)->sort()->values();
        $rowsPerWeek = $this->rows($user, $sortedWeeks->first(), $sortedWeeks->last()->addWeek())
            ->groupBy(fn (stdClass $row) => $calendar->weekOf(CarbonImmutable::parse($row->started_at, 'UTC'))->toDateString());

        return collect($weeks)
            ->map(fn (CarbonImmutable $week) => $week->toDateString())
            ->mapWithKeys(fn (string $monday) => [$monday => $this->perMuscle($rowsPerWeek->get($monday, collect()))])
            ->all();
    }

    /**
     * What each Muscle got from the Workouts started from the first moment up to the second, by name. Muscles without counting Sets are left out.
     *
     * @return array<string, Attributed>
     */
    public function between(User $user, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->perMuscle($this->rows($user, $from, $until));
    }

    /**
     * What each Muscle got per Workout started from the first moment up to the second: one row per Workout and Muscle with its started_at, muscle, total and direct.
     *
     * @return Collection<int, stdClass>
     */
    private function rows(User $user, CarbonImmutable $from, CarbonImmutable $until): Collection
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
            ->whereIn('workout_sets.kind', $this->kinds)
            ->select('workouts.started_at', 'exercise_muscles.muscle')
            ->selectRaw(
                'SUM(('.$this->amountPerSet.') * CASE WHEN exercise_muscles.role = ? THEN 1 ELSE 0.5 END) AS total',
                [MuscleRole::Primary->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN exercise_muscles.role = ? THEN '.$this->amountPerSet.' ELSE 0 END) AS direct',
                [MuscleRole::Primary->value],
            )
            ->groupBy('workouts.id', 'workouts.started_at', 'exercise_muscles.muscle')
            ->orderBy('exercise_muscles.muscle')
            ->toBase()
            ->get();
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array<string, Attributed>
     */
    private function perMuscle(Collection $rows): array
    {
        $muscles = [];

        foreach ($rows as $row) {
            $muscles[$row->muscle] ??= ['total' => 0.0, 'direct' => 0.0];
            $muscles[$row->muscle]['total'] += (float) $row->total;
            $muscles[$row->muscle]['direct'] += (float) $row->direct;
        }

        return $muscles;
    }
}
