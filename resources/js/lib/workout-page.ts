import type { Page } from '@inertiajs/core';
import { parentScreens } from '@/lib/parent-screens';
import { isInProgress } from '@/lib/utils';
import { show as showWorkout } from '@/routes/workouts';
import type { PageShell, Workout } from '@/types';

/**
 * Whether the workouts/show page shows the Workout in progress rather than a finished Workout.
 * Before its Workout arrives, the address tells, with the shared workoutInProgressId:
 * another Workout's address opens a finished Workout; the Workout in progress's own address,
 * or Start Workout's, opens the Workout in progress.
 */
export function showsWorkoutInProgress({ url, props }: Page): boolean {
    /** Undefined while an instant visit loads the page. */
    const workout = props.workout as Workout | undefined;

    if (workout) {
        return isInProgress(workout);
    }

    const workoutId = workoutIdOf(url);

    return workoutId === null || workoutId === props.workoutInProgressId;
}

/** The id of the Workout whose address this is, or null for another address, such as Start Workout's. */
function workoutIdOf(url: string): number | null {
    const { pathname } = new URL(url, 'http://localhost');
    const lastSegment = Number(pathname.split('/').at(-1));

    return Number.isInteger(lastSegment) &&
        showWorkout.url(lastSegment) === pathname
        ? lastSegment
        : null;
}

/**
 * The workouts/show page's layout props. The Workout in progress is the Workout tab's own screen;
 * a finished Workout is a detail of Workout history.
 * Set from app.tsx's default layout, since a page's own layout props see its props but not its address.
 */
export function workoutPageShell(page: Page): PageShell {
    return showsWorkoutInProgress(page)
        ? { tab: 'workout', title: 'Workout in progress' }
        : {
              tab: 'workout',
              title: 'Workout',
              parent: parentScreens.workoutHistory,
          };
}
