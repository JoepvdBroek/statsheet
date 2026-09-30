import type { Exercise } from './exercise';

/** The Routine a Workout was started from. */
export type WorkoutRoutine = { id: number; name: string; archived: boolean };

export type WorkoutSet = {
    id: number;
    /** Empty for a Set added during the Workout. */
    target_reps: number | null;
    /** kg; the added load for a Bodyweight Exercise. */
    target_weight: number | null;
    actual_reps: number | null;
    actual_weight: number | null;
    is_warm_up: boolean;
    /** A Set is done exactly when it has an Actual. */
    done: boolean;
    /** The server's verdict: done, with reps and weight at or above the Target (or no Target). */
    meets_target: boolean;
};

export type WorkoutExercise = {
    id: number;
    exercise: Exercise;
    /** In order. */
    sets: WorkoutSet[];
};

export type Workout = {
    id: number;
    status: 'in_progress' | 'finished';
    started_at: string;
    finished_at: string | null;
    /** The Routine it was started from, even when archived; empty when started empty. */
    routine: WorkoutRoutine | null;
    /** The owner's Bodyweight in kg when the Workout started. */
    bodyweight: number | null;
    note: string | null;
    /** In the order they are trained. */
    exercises: WorkoutExercise[];
};

/** A Workout as the history lists it, without its Exercises and Sets. */
export type WorkoutSummary = {
    id: number;
    status: 'in_progress' | 'finished';
    started_at: string;
    /** The Routine it was started from, even when archived; empty when started empty. */
    routine: WorkoutRoutine | null;
    note: string | null;
};
