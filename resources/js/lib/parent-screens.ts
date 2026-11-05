import { home } from '@/routes';
import { index as exercisesIndex } from '@/routes/exercises';
import { index as reviewsIndex } from '@/routes/reviews';
import { hub as statsHub } from '@/routes/statistics';
import { index as workoutsIndex } from '@/routes/workouts';
import type { ParentScreen } from '@/types';

/** The fixed screens that detail screens' back chevrons go to. */
export const parentScreens = {
    routines: { title: 'Routines', href: home(), component: 'home' },
    stats: { title: 'Stats', href: statsHub(), component: 'statistics/hub' },
    workoutHistory: {
        title: 'Workout history',
        href: workoutsIndex(),
        component: 'workouts/index',
    },
    exercises: {
        title: 'Exercises',
        href: exercisesIndex(),
        component: 'exercises/index',
    },
    reviews: {
        title: 'Weekly Reviews',
        href: reviewsIndex(),
        component: 'reviews/index',
    },
} satisfies Record<string, ParentScreen>;
