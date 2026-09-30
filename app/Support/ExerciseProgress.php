<?php

namespace App\Support;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Exercise's progress: its Personal Records, the records each Set beat, its best per Workout over time, and its
 * recent performances. newRecordsIn() gives the records beaten across all the Exercises of one Workout.
 * It is computed from the log when read, with nothing stored, so edits and deletions show up straight away.
 * The done Sets are loaded once, when it is made.
 *
 * Only done, non-warm-up Sets count towards records and progress.
 * Estimated 1RM is Epley's weight × (1 + reps / 30), for Sets of at most 12 reps.
 * For a Bodyweight Exercise, heaviest and reps-at-weight use the added load, while Estimated 1RM and set tonnage use
 * the Workout's Bodyweight plus the added load, a missing Bodyweight counting as 0.
 *
 * @phpstan-type MeasuredSet array{id: int, exercise_id: int, workout: Workout, reps: int, added_load: float, warm_up: bool, e1rm: float|null, tonnage: float}
 * @phpstan-type Measure 'heaviest'|'e1rm'|'reps'|'tonnage'
 */
class ExerciseProgress
{
    /**
     * The Personal Record measures, in the order they are listed.
     *
     * @var list<Measure>
     */
    private const array MEASURES = ['heaviest', 'e1rm', 'reps', 'tonnage'];

    /**
     * @param  list<MeasuredSet>  $doneSets  The Exercise's done Sets, in the order they were performed
     */
    private function __construct(private array $doneSets) {}

    /**
     * The progress of an Exercise, loading its done Sets, or only those of Workouts started before the given moment.
     */
    public static function of(Exercise $exercise, ?CarbonInterface $before = null): self
    {
        return new self(self::doneSets(
            $exercise->workoutExercises()->when(
                $before,
                fn (Builder $performances) => $performances->where('workouts.started_at', '<', CarbonImmutable::instance($before)->utc()),
            ),
        ));
    }

    /**
     * The Personal Records each done Set of the Workout beat, keyed by Set id, for the Sets that beat any.
     * It loads the done Sets of the Workout's Exercises up to the Workout at once.
     *
     * @return array<int, non-empty-list<Measure>>
     */
    public static function newRecordsIn(Workout $workout): array
    {
        $performances = WorkoutExercise::query()
            ->whereIn('workout_exercises.exercise_id', $workout->exercises()->select('exercise_id'))
            ->where('workouts.started_at', '<=', $workout->started_at);

        $perExercise = [];

        foreach (self::doneSets($performances) as $set) {
            $perExercise[$set['exercise_id']][] = $set;
        }

        $newRecords = [];

        foreach ($perExercise as $sets) {
            $newRecords += (new self($sets))->newRecords();
        }

        return $newRecords;
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
            $weight = $this->weightKey($set['added_load']);
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
     * When Personal Records were beaten: for each Set that beat any, the start of its Workout and the records it beat, in the order they were performed.
     *
     * @return list<array{started_at: CarbonImmutable, beaten: non-empty-list<Measure>}>
     */
    public function recordsBeaten(): array
    {
        $newRecords = $this->newRecords();
        $recordsBeaten = [];

        foreach ($this->doneSets as $set) {
            if (isset($newRecords[$set['id']])) {
                $recordsBeaten[] = ['started_at' => $set['workout']->started_at, 'beaten' => $newRecords[$set['id']]];
            }
        }

        return $recordsBeaten;
    }

    /**
     * The Personal Records each Set beat, keyed by Set id, for the Sets that beat any, in the order of the measures.
     *
     * A Set beats a record when it does better on that measure than every earlier qualifying Set, where earlier means
     * from a Workout that started earlier or from an earlier position in the same Workout. Equalling a record doesn't
     * beat it. A measure with nothing earlier to beat is never beaten: not by the first qualifying Set, not on
     * Estimated 1RM before a Set of at most 12 reps, and not on most reps at a weight before a Set at that weight.
     *
     * @return array<int, non-empty-list<Measure>>
     */
    public function newRecords(): array
    {
        $heaviest = null;
        $e1rm = null;
        $tonnage = null;
        $bestRepsAtWeight = [];
        $newRecords = [];

        foreach ($this->qualifying($this->doneSets) as $set) {
            $weight = $this->weightKey($set['added_load']);

            $beaten = array_keys(array_filter([
                'heaviest' => $heaviest !== null && $set['added_load'] > $heaviest,
                'e1rm' => $e1rm !== null && $set['e1rm'] !== null && $set['e1rm'] > $e1rm,
                'reps' => isset($bestRepsAtWeight[$weight]) && $set['reps'] > $bestRepsAtWeight[$weight],
                'tonnage' => $tonnage !== null && $set['tonnage'] > $tonnage,
            ]));

            if ($beaten !== []) {
                $newRecords[$set['id']] = $beaten;
            }

            $heaviest = max($heaviest ?? $set['added_load'], $set['added_load']);
            $e1rm = $this->best([$e1rm, $set['e1rm']]);
            $bestRepsAtWeight[$weight] = max($bestRepsAtWeight[$weight] ?? 0, $set['reps']);
            $tonnage = max($tonnage ?? $set['tonnage'], $set['tonnage']);
        }

        return $newRecords;
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
     * Each lists the Personal Records its Sets beat, in the order of the measures.
     *
     * @return list<array{workout_id: int, started_at: string, routine: string|null, e1rm: float|null, new_records: list<Measure>, sets: list<array{reps: int, weight: float, warm_up: bool, top: bool}>}>
     */
    public function recentPerformances(): array
    {
        $newRecords = $this->newRecords();
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
                'new_records' => array_values(array_intersect(
                    self::MEASURES,
                    array_merge(...array_map(fn (array $set) => $newRecords[$set['id']] ?? [], $sets)),
                )),
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
     * A weight as a key that is the same for equal weights.
     */
    private function weightKey(float $weight): string
    {
        return number_format($weight, 2, '.', '');
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
     * The done Sets of the performances with the loads the measures use, in the order they were performed.
     *
     * @param  Builder<WorkoutExercise>|HasMany<WorkoutExercise, Exercise>  $performances
     * @return list<MeasuredSet>
     */
    private static function doneSets(Builder|HasMany $performances): array
    {
        $performances = $performances
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->orderBy('workouts.started_at')
            ->orderBy('workouts.id')
            ->orderBy('workout_exercises.position')
            ->select('workout_exercises.*')
            ->with('workout.routine', 'exercise', 'sets')
            ->get();

        $sets = [];

        foreach ($performances as $performed) {
            foreach ($performed->sets as $set) {
                $measured = self::measure($set, $performed);

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
    private static function measure(WorkoutSet $set, WorkoutExercise $performed): ?array
    {
        if ($set->actual_reps === null || $set->actual_weight === null) {
            return null;
        }

        $addedLoad = (float) $set->actual_weight;
        $load = $performed->exercise->is_bodyweight ? (float) $performed->workout->bodyweight + $addedLoad : $addedLoad;

        return [
            'id' => $set->id,
            'exercise_id' => $performed->exercise_id,
            'workout' => $performed->workout,
            'reps' => $set->actual_reps,
            'added_load' => $addedLoad,
            'warm_up' => $set->is_warm_up,
            'e1rm' => $set->actual_reps <= 12 ? round($load * (1 + $set->actual_reps / 30), 2) : null,
            'tonnage' => round($set->actual_reps * $load, 2),
        ];
    }
}
