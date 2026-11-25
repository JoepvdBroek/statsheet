/** A Muscle's Set Count over some period, as the statistics pages receive it. */
export type MuscleSetCount = {
    /** A multiple of 0.5: secondary Muscles count half. */
    set_count: number;
    /** The part of the Set Count from Exercises where the Muscle is primary. */
    direct_sets: number;
};
