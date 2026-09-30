import type { Exercise } from './exercise';

export type RoutineSet = {
    target_reps: number;
    /** kg, up to two decimals; the added load for a Bodyweight Exercise. */
    target_weight: number;
    is_warm_up: boolean;
};

export type RoutineExercise = {
    exercise: Exercise;
    /** In order. */
    sets: RoutineSet[];
};

export type Routine = {
    id: number;
    name: string;
    archived: boolean;
    /** In the order they are trained. */
    exercises: RoutineExercise[];
};
