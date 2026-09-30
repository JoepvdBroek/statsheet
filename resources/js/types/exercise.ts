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
