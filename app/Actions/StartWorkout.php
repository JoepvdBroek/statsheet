<?php

namespace App\Actions;

use App\Enums\SetKind;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Support\Facades\DB;

/**
 * The only way a Workout is created. An owner has at most one Workout in progress, so starting while one is open returns that one instead.
 */
class StartWorkout
{
    /**
     * Start an empty Workout, without Exercises, snapshotting the owner's current Bodyweight.
     *
     * The returned Workout's `wasRecentlyCreated` tells whether it is new or the one already in progress.
     */
    public function empty(User $user): Workout
    {
        return $user->workouts()->inProgress()->first()
            ?? $user->workouts()->create([
                'started_at' => now(),
                'bodyweight' => $user->bodyweight,
            ]);
    }

    /**
     * Start a Workout from a Routine, snapshotting the owner's current Bodyweight and the Routine's Exercises and set counts (ADR 0001).
     * Each Set's Target is Pre-filled from the most recent other Workout containing its Exercise.
     *
     * The returned Workout's `wasRecentlyCreated` tells whether it is new or the one already in progress.
     */
    public function fromRoutine(User $user, Routine $routine): Workout
    {
        return $user->workouts()->inProgress()->first()
            ?? DB::transaction(function () use ($user, $routine) {
                $plan = $routine->exercises()->with('exercise', 'sets')->get();

                $lastPerformances = $plan->pluck('exercise')->unique('id')
                    ->mapWithKeys(fn (Exercise $exercise) => [$exercise->id => $exercise->lastPerformance()]);

                $workout = $user->workouts()->create([
                    'routine_id' => $routine->id,
                    'started_at' => now(),
                    'bodyweight' => $user->bodyweight,
                ]);

                foreach ($plan as $exercisePosition => $planned) {
                    $this->copyPlannedExercise($workout, $planned, $exercisePosition, $lastPerformances[$planned->exercise_id]);
                }

                return $workout;
            });
    }

    /**
     * Add a planned Exercise to the Workout with the Routine's set count, each Set Pre-filled from the same Set last time.
     */
    private function copyPlannedExercise(Workout $workout, RoutineExercise $planned, int $position, ?WorkoutExercise $lastPerformance): void
    {
        $performed = $workout->exercises()->create([
            'exercise_id' => $planned->exercise_id,
            'position' => $position,
        ]);

        foreach ($planned->sets as $setPosition => $plannedSet) {
            $performed->sets()->create([
                ...$this->preFill($plannedSet, $lastPerformance?->sets->get($setPosition)),
                'position' => $setPosition,
            ]);
        }
    }

    /**
     * The Target and kind of a Set: carried over from the same Set last time when it has one, otherwise the Routine's.
     *
     * @return array{target_reps: int, target_weight: string, kind: SetKind}
     */
    private function preFill(RoutineSet $plannedSet, ?WorkoutSet $lastTime): array
    {
        $carriedOver = $lastTime?->carriedOverTarget();

        if ($carriedOver === null) {
            return [
                'target_reps' => $plannedSet->target_reps,
                'target_weight' => $plannedSet->target_weight,
                'kind' => $plannedSet->kind,
            ];
        }

        return [
            'target_reps' => $carriedOver['reps'],
            'target_weight' => $carriedOver['weight'],
            'kind' => $lastTime->kind,
        ];
    }
}
