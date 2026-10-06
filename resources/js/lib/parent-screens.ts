import { index as exercisesIndex } from '@/routes/exercises';
import { index as reviewsIndex } from '@/routes/reviews';
import { index as routinesIndex } from '@/routes/routines';
import { hub as statsHub } from '@/routes/statistics';
import { index as workoutsIndex } from '@/routes/workouts';
import type { ParentScreen } from '@/types';

/** The fixed screens that detail screens' back chevrons go to. */
export const parentScreens = {
    routines: { title: 'Routines', href: routinesIndex() },
    stats: { title: 'Stats', href: statsHub() },
    workoutHistory: { title: 'Workout history', href: workoutsIndex() },
    exercises: { title: 'Exercises', href: exercisesIndex() },
    reviews: { title: 'Weekly Reviews', href: reviewsIndex() },
} satisfies Record<string, ParentScreen>;
