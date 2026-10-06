import { Form, Head, Link, usePage } from '@inertiajs/react';
import { PlayIcon, PlusIcon, ScaleIcon } from 'lucide-react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/workouts';
import type { PageShell } from '@/types';

export default function Dashboard({
    workoutInProgress,
    bodyweightNudge,
}: {
    /** The owner's one Workout in progress, if any. */
    workoutInProgress: { id: number; started_at: string } | null;
    /** Whether the owner has no Bodyweight set yet. */
    bodyweightNudge: boolean;
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

                {bodyweightNudge ? (
                    <Alert>
                        <ScaleIcon aria-hidden="true" />
                        <AlertTitle>Set your Bodyweight</AlertTitle>
                        <AlertDescription>
                            <p>
                                Until you do, Bodyweight Exercises count only
                                their added load toward Volume.{' '}
                                <Link
                                    href={editProfile()}
                                    className="font-medium text-foreground underline underline-offset-4"
                                >
                                    Set it in your profile
                                </Link>
                            </p>
                        </AlertDescription>
                    </Alert>
                ) : null}
            </div>
        </>
    );
}

Dashboard.layout = { tab: null, title: 'Dashboard' } satisfies PageShell;
