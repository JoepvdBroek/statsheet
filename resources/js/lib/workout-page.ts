import type { Page } from '@inertiajs/core';
import { parentScreens } from '@/lib/parent-screens';
import { isInProgress } from '@/lib/utils';
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

    const workoutId = /^\/workouts\/(\d+)(?:[?#]|$)/.exec(url)?.[1];

    return (
        workoutId === undefined ||
        Number(workoutId) === (props.workoutInProgressId as number | null)
    );
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
