import type { Exercise } from './exercise';
import type { SetKind } from './workout';

export type RoutineSet = {
    target_reps: number;
    /** kg, up to two decimals; the added load for a Bodyweight Exercise. */
    target_weight: number;
    kind: SetKind;
};

export type RoutineExercise = {
    exercise: Exercise;
    /** In order. */
    sets: RoutineSet[];
    /** Its Superset's number (1, 2, … in order of first appearance), shared with the exercises next to it in the same Superset; null when in none. */
    superset: number | null;
};

export type Routine = {
    id: number;
    name: string;
    archived: boolean;
    /** Start of the latest finished Workout from it; empty if never done. Only on the Routines home's active list. */
    last_done?: string | null;
    /** In the order they are trained. */
    exercises: RoutineExercise[];
};
