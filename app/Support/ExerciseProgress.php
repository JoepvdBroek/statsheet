<?php

namespace App\Support;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;

/**
 * An Exercise's progress: its Personal Records, its best per Workout over time, and its recent performances.
 * It is computed from the log when read, with nothing stored, so edits and deletions show up straight away.
 * The done Sets are loaded once, when it is made.
 *
 * Only done, non-warm-up Sets count towards records and progress.
 * Estimated 1RM is Epley's weight × (1 + reps / 30), for Sets of at most 12 reps.
 * For a Bodyweight Exercise, heaviest and reps-at-weight use the added load, while Estimated 1RM and set tonnage use
 * the Workout's Bodyweight plus the added load, a missing Bodyweight counting as 0.
 *
 * @phpstan-type MeasuredSet array{workout: Workout, reps: int, added_load: float, warm_up: bool, e1rm: float|null, tonnage: float}
 */
class ExerciseProgress
{
    /**
     * @param  list<MeasuredSet>  $doneSets  The Exercise's done Sets, in the order they were performed
     */
    private function __construct(private array $doneSets) {}

    /**
     * The progress of an Exercise, loading its done Sets.
     */
    public static function of(Exercise $exercise): self
    {
        return new self(self::doneSets($exercise));
    }

    /**
     * The Exercise's Personal Records, each empty until a Set qualifies.
     *
     * "Most reps at a weight" is a table from weight to best reps, lightest first.
     *
     * @return array{heaviest: float|null, e1rm: float|null, reps_at_weight: list<array{weight: float, reps: int}>, tonnage: float|null}
     */
    public function records(): array
    {
        $sets = $this->qualifying($this->doneSets);

        $bestRepsAtWeight = [];

        foreach ($sets as $set) {
            $weight = number_format($set['added_load'], 2, '.', '');
            $bestRepsAtWeight[$weight] = max($bestRepsAtWeight[$weight] ?? 0, $set['reps']);
        }

        ksort($bestRepsAtWeight, SORT_NUMERIC);

        return [
            'heaviest' => $this->best(array_column($sets, 'added_load')),
            'e1rm' => $this->best(array_column($sets, 'e1rm')),
            'reps_at_weight' => array_map(
                fn (string $weight, int $reps) => ['weight' => (float) $weight, 'reps' => $reps],
                array_map('strval', array_keys($bestRepsAtWeight)),
                array_values($bestRepsAtWeight),
            ),
            'tonnage' => $this->best(array_column($sets, 'tonnage')),
        ];
    }

    /**
     * The best Estimated 1RM and heaviest weight of each Workout with a qualifying Set, oldest first, in progress or not.
     *
     * @return list<array{workout_id: int, started_at: string, e1rm: float|null, heaviest: float}>
     */
    public function perWorkout(): array
    {
        $points = [];

        foreach ($this->perWorkoutSets($this->qualifying($this->doneSets)) as $sets) {
            $workout = $sets[0]['workout'];
            $points[] = [
                'workout_id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
                'e1rm' => $this->best(array_column($sets, 'e1rm')),
                'heaviest' => max(array_column($sets, 'added_load')),
            ];
        }

        return $points;
    }

    /**
     * The Exercise's done Sets in its 10 most recent Workouts, newest first, for looking back at past performances.
     * Warm-up Sets are listed but never the top Set, which is the first Set with the Workout's best Estimated 1RM.
     *
     * @return list<array{workout_id: int, started_at: string, routine: string|null, e1rm: float|null, sets: list<array{reps: int, weight: float, warm_up: bool, top: bool}>}>
     */
    public function recentPerformances(): array
    {
        $performances = [];

        foreach (array_slice(array_reverse($this->perWorkoutSets($this->doneSets)), 0, 10) as $sets) {
            $workout = $sets[0]['workout'];
            $best = $this->best(array_column($this->qualifying($sets), 'e1rm'));
            $top = null;

            foreach ($sets as $index => $set) {
                if ($best !== null && ! $set['warm_up'] && $set['e1rm'] === $best) {
                    $top = $index;

                    break;
                }
            }

            $performances[] = [
                'workout_id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
                'routine' => $workout->routine?->name,
                'e1rm' => $best,
                'sets' => array_map(fn (array $set, int $index) => [
                    'reps' => $set['reps'],
                    'weight' => $set['added_load'],
                    'warm_up' => $set['warm_up'],
                    'top' => $index === $top,
                ], $sets, array_keys($sets)),
            ];
        }

        return $performances;
    }

    /**
     * The highest of the values, ignoring empty ones. Empty when there is none.
     *
     * @param  list<float|null>  $values
     */
    private function best(array $values): ?float
    {
        $values = array_filter($values, fn (?float $value) => $value !== null);

        return $values === [] ? null : max($values);
    }

    /**
     * The Sets Personal Records count: the non-warm-up ones.
     *
     * @param  list<MeasuredSet>  $sets
     * @return list<MeasuredSet>
     */
    private function qualifying(array $sets): array
    {
        return array_values(array_filter($sets, fn (array $set) => ! $set['warm_up']));
    }

    /**
     * The Sets grouped by Workout, keeping their order.
     *
     * @param  list<MeasuredSet>  $sets
     * @return list<non-empty-list<MeasuredSet>>
     */
    private function perWorkoutSets(array $sets): array
    {
        $grouped = [];

        foreach ($sets as $set) {
            $grouped[$set['workout']->id][] = $set;
        }

        return array_values($grouped);
    }

    /**
     * The Exercise's done Sets with the loads the measures use, in the order they were performed.
     *
     * @return list<MeasuredSet>
     */
    private static function doneSets(Exercise $exercise): array
    {
        $performances = $exercise->workoutExercises()
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->orderBy('workouts.started_at')
            ->orderBy('workouts.id')
            ->orderBy('workout_exercises.position')
            ->select('workout_exercises.*')
            ->with('workout.routine', 'sets')
            ->get();

        $sets = [];

        foreach ($performances as $performed) {
            foreach ($performed->sets as $set) {
                $measured = self::measure($set, $performed, $exercise->is_bodyweight);

                if ($measured !== null) {
                    $sets[] = $measured;
                }
            }
        }

        return $sets;
    }

    /**
     * A done Set with the loads the measures use. Empty for a Set that wasn't done.
     *
     * @return MeasuredSet|null
     */
    private static function measure(WorkoutSet $set, WorkoutExercise $performed, bool $isBodyweight): ?array
    {
        if ($set->actual_reps === null || $set->actual_weight === null) {
            return null;
        }

        $addedLoad = (float) $set->actual_weight;
        $load = $isBodyweight ? (float) $performed->workout->bodyweight + $addedLoad : $addedLoad;

        return [
            'workout' => $performed->workout,
            'reps' => $set->actual_reps,
            'added_load' => $addedLoad,
            'warm_up' => $set->is_warm_up,
            'e1rm' => $set->actual_reps <= 12 ? round($load * (1 + $set->actual_reps / 30), 2) : null,
            'tonnage' => round($set->actual_reps * $load, 2),
        ];
    }
}
