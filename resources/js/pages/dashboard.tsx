import { Form, Head, Link, usePage } from '@inertiajs/react';
import { PlayIcon, PlusIcon } from 'lucide-react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { Button } from '@/components/ui/button';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { formatDateTime } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show } from '@/routes/workouts';

export default function Dashboard({
    workoutInProgress,
}: {
    /** The owner's one Workout in progress, if any. */
    workoutInProgress: { id: number; started_at: string } | null;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground sm:flex-row sm:items-center sm:justify-between">
                    {workoutInProgress ? (
                        <>
                            <div className="space-y-0.5">
                                <h2 className="text-base font-medium">
                                    Workout in progress
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Started{' '}
                                    {formatDateTime(
                                        workoutInProgress.started_at,
                                        auth.user.timezone,
                                    )}
                                </p>
                            </div>
                            <Button
                                asChild
                                size="lg"
                                className="h-12 text-base"
                            >
                                <Link href={show(workoutInProgress.id)}>
                                    <PlayIcon aria-hidden="true" />
                                    Resume workout
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <div className="space-y-0.5">
                                <h2 className="text-base font-medium">
                                    Ready to train?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Start an empty Workout and add Exercises as
                                    you go.
                                </p>
                            </div>
                            <Form {...WorkoutController.store.form()}>
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="h-12 w-full text-base sm:w-auto"
                                        disabled={processing}
                                    >
                                        <PlusIcon aria-hidden="true" />
                                        Start empty workout
                                    </Button>
                                )}
                            </Form>
                        </>
                    )}
                </section>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
