import { Form, Head, Link, usePage } from '@inertiajs/react';
import { PlayIcon, PlusIcon, ScaleIcon } from 'lucide-react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { StatBlock } from '@/components/statsheet/stat-block';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { capitalize, formatDate, formatDateTime } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as goalsIndex } from '@/routes/goals';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/workouts';

type ThisWeek = {
    /** The Week's Monday, as a date. */
    starts_on: string;
    /** Each Muscle with Volume or a Goal in force, most Volume first. */
    muscles: {
        muscle: string;
        /** kg */
        volume: number;
        /** The weekly minimum in kg of the Goal in force, if any. */
        goal: number | null;
        /** Whether the Goal is met; empty without a Goal. */
        met: boolean | null;
    }[];
};

const volumeFormat = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

export default function Dashboard({
    workoutInProgress,
    thisWeek,
    bodyweightNudge,
}: {
    /** The owner's one Workout in progress, if any. */
    workoutInProgress: { id: number; started_at: string } | null;
    /** This Week's Volume per Muscle, live: Sets in the Workout in progress count too. */
    thisWeek: ThisWeek;
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

                <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
                    <div className="flex items-start justify-between gap-4">
                        <div className="space-y-0.5">
                            <h2 className="text-base font-medium">This Week</h2>
                            <p className="text-sm text-muted-foreground">
                                Volume per Muscle since Monday{' '}
                                {formatDate(thisWeek.starts_on)}, including the
                                Workout in progress.
                            </p>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={goalsIndex()}>Goals</Link>
                        </Button>
                    </div>

                    {thisWeek.muscles.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            No Volume yet this Week. Mark a Set done to see it
                            here.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                            {thisWeek.muscles.map(
                                ({ muscle, volume, goal, met }) => (
                                    <StatBlock
                                        key={muscle}
                                        size="sm"
                                        tone="live"
                                        value={volumeFormat.format(volume)}
                                        unit="kg"
                                        label={capitalize(muscle)}
                                        delta={
                                            goal === null
                                                ? undefined
                                                : met
                                                  ? `Goal met · ${volumeFormat.format(goal)}`
                                                  : `${volumeFormat.format(goal - volume)} to go`
                                        }
                                    />
                                ),
                            )}
                        </div>
                    )}
                </section>
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
