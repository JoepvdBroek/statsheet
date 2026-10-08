import type { SetKind } from './workout';

export type MuscleRole = 'primary' | 'secondary';

export type ExerciseMuscle = {
    /** One of the 17 Muscles, in lower case as in the glossary. */
    muscle: string;
    role: MuscleRole;
};

export type Exercise = {
    id: number;
    name: string;
    equipment: string | null;
    is_bodyweight: boolean;
    archived: boolean;
    /** Primary Muscles first. */
    muscles: ExerciseMuscle[];
};

/** A Personal Record measure: heaviest weight, best Estimated 1RM, most reps at a weight, or best set tonnage. */
export type PersonalRecordMeasure = 'heaviest' | 'e1rm' | 'reps' | 'tonnage';

/** An Exercise's Personal Records, from done, non-warm-up Sets; each empty until a Set qualifies. */
export type PersonalRecords = {
    /** kg; the added load for a Bodyweight Exercise. */
    heaviest: number | null;
    /** Best Estimated 1RM in kg, from Sets of at most 12 reps; includes Bodyweight for a Bodyweight Exercise. */
    e1rm: number | null;
    /** Most reps at each weight, lightest first; weight is the added load for a Bodyweight Exercise. */
    reps_at_weight: { weight: number; reps: number }[];
    /** Best set tonnage (reps × weight) in kg; includes Bodyweight for a Bodyweight Exercise. */
    tonnage: number | null;
};

/** An Exercise's best Estimated 1RM and heaviest weight in one Workout. */
export type ExerciseProgressPoint = {
    workout_id: number;
    started_at: string;
    e1rm: number | null;
    heaviest: number;
};

/** An Exercise's done Sets in one past Workout. */
export type ExercisePerformance = {
    workout_id: number;
    started_at: string;
    /** The Routine the Workout was started from; empty when started empty. */
    routine: string | null;
    /** The Workout's best Estimated 1RM in kg. */
    e1rm: number | null;
    /** The Intensity of its heaviest non-warm-up Set, as a whole percentage of the Expected 1RM; empty without an Expected 1RM. */
    intensity: number | null;
    /** The Average Weight in kg of its non-warm-up Sets; includes Bodyweight for a Bodyweight Exercise. */
    average_weight: number | null;
    /** The Personal Records its Sets beat. */
    new_records: PersonalRecordMeasure[];
    /** In order; weight is the added load for a Bodyweight Exercise. */
    sets: { reps: number; weight: number; kind: SetKind; top: boolean }[];
};
