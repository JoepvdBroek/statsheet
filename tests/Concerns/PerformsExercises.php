<?php

namespace Tests\Concerns;

use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Factories\WorkoutSetFactory;

/**
 * Builds a Workout's log in tests: Exercises performed after the others, each with its Sets in order.
 */
trait PerformsExercises
{
    /**
     * Add an Exercise after the Workout's others, performed with the given Sets in order.
     *
     * @param  list<WorkoutSetFactory>  $sets
     */
    protected function perform(Workout $workout, Exercise $exercise, array $sets): void
    {
        $performed = WorkoutExercise::factory()
            ->for($workout)
            ->for($exercise)
            ->create(['position' => $workout->exercises()->count()]);

        foreach ($sets as $position => $set) {
            $set->for($performed)->create(['position' => $position]);
        }
    }

    /**
     * A Set done as planned: its Target and Actual are both the given reps × weight.
     */
    protected function doneSet(int $reps, string $weight): WorkoutSetFactory
    {
        return WorkoutSet::factory()->state(['target_reps' => $reps, 'target_weight' => $weight])->done();
    }
}
