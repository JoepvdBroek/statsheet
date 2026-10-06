import { Head, InfiniteScroll, Link, usePage } from '@inertiajs/react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { StartEmptyWorkoutButton } from '@/components/start-empty-workout-button';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/utils';
import type { PageShell, WorkoutSummary } from '@/types';

export default function WorkoutsIndex({
    workouts,
}: {
    workouts: { data: WorkoutSummary[] };
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Workout history" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <StartEmptyWorkoutButton />

                <p className="text-sm text-muted-foreground">
                    Every Workout you logged, newest first.
                </p>

                {workouts.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No Workouts yet. Start an empty one above, or one from a
                        Routine.
                    </p>
                ) : (
                    <InfiniteScroll
                        data="workouts"
                        onlyNext
                        as="ul"
                        className="flex flex-col gap-2"
                    >
                        {workouts.data.map((workout) => (
                            <WorkoutRow
                                key={workout.id}
                                workout={workout}
                                timeZone={auth.user.timezone}
                            />
                        ))}
                    </InfiniteScroll>
                )}
            </div>
        </>
    );
}

function WorkoutRow({
    workout,
    timeZone,
}: {
    workout: WorkoutSummary;
    timeZone: string;
}) {
    return (
        <li className="flex items-start gap-3 rounded-xl border bg-card px-4 py-3 text-card-foreground">
            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                <Link
                    href={WorkoutController.show(workout.id)}
                    className="truncate rounded-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                >
                    {formatDateTime(workout.started_at, timeZone)}
                </Link>
                <span className="truncate text-sm text-muted-foreground">
                    {workout.routine
                        ? `${workout.routine.name}${workout.routine.archived ? ' (archived)' : ''}`
                        : 'Empty workout'}
                </span>
                {workout.note ? (
                    <p className="line-clamp-2 text-sm whitespace-pre-line">
                        {workout.note}
                    </p>
                ) : null}
            </div>
            {workout.status === 'in_progress' ? (
                <Badge className="shrink-0">In progress</Badge>
            ) : null}
        </li>
    );
}

WorkoutsIndex.layout = {
    tab: 'workout',
    title: 'Workout history',
} satisfies PageShell;
