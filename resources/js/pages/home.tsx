import { Head, Link, router, usePage } from '@inertiajs/react';
import { PlayIcon, PlusIcon, ScaleIcon, SettingsIcon } from 'lucide-react';
import RoutineController from '@/actions/App/Http/Controllers/RoutineController';
import { StartEmptyWorkoutButton } from '@/components/start-empty-workout-button';
import { RoutineCard } from '@/components/statsheet/routine-card';
import { StatBlock } from '@/components/statsheet/stat-block';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatKg, formatLastDone } from '@/lib/utils';
import { home } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/workouts';
import type { PageShell, Routine } from '@/types';

type HomeProps = {
    /** The owner's Routines in use with their Last Done, the one most likely due first. */
    routines: Routine[];
    /** The owner's Archived Routines, only when the toggle asks for them. */
    archivedRoutines: Routine[] | null;
    /** The owner's one Workout in progress, if any. */
    workoutInProgress: {
        id: number;
        started_at: string;
        /** Empty for an empty Workout. */
        routine_name: string | null;
    } | null;
    /** Whether the owner has no Bodyweight set yet. */
    bodyweightNudge: boolean;
    thisWeek: ThisWeek;
};

type ThisWeek = {
    /** Workouts started this Week, the one in progress included. */
    workouts: number;
    /** This Week's total Volume in kg, done Sets of the Workout in progress included. */
    volume: number;
    /** Last Week's total Volume in kg, of Workouts started up to the same weekday and time. */
    last_week_volume: number;
};

export default function Home({
    routines,
    archivedRoutines,
    workoutInProgress,
    bodyweightNudge,
    thisWeek,
}: HomeProps) {
    const { auth } = usePage().props;
    const showsArchived = archivedRoutines !== null;

    return (
        <>
            <Head title="Routines" />

            <div className="flex flex-col gap-4 p-4">
                {workoutInProgress ? (
                    <section className="flex items-center justify-between gap-4 rounded-xl border border-primary/40 bg-card p-4 text-card-foreground">
                        <div className="min-w-0 space-y-0.5">
                            <p className="text-xs font-medium tracking-[0.08em] text-primary uppercase">
                                Workout in progress
                            </p>
                            <h2 className="truncate text-base font-medium">
                                {workoutInProgress.routine_name ??
                                    'Empty workout'}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Started{' '}
                                {formatDateTime(
                                    workoutInProgress.started_at,
                                    auth.user.timezone,
                                )}
                            </p>
                        </div>
                        <Button asChild className="shrink-0">
                            <Link href={show(workoutInProgress.id)}>
                                <PlayIcon aria-hidden="true" />
                                Resume
                            </Link>
                        </Button>
                    </section>
                ) : null}

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

                <ThisWeekStrip thisWeek={thisWeek} />

                {routines.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No Routines yet. Plan your first one.
                    </p>
                ) : (
                    <RoutineCards routines={routines} />
                )}

                <StartEmptyWorkoutButton />

                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="self-center text-muted-foreground"
                >
                    <Link
                        href={home({
                            query: showsArchived ? {} : { archived: 1 },
                        })}
                        only={['archivedRoutines']}
                        preserveScroll
                        aria-expanded={showsArchived}
                    >
                        {showsArchived ? 'Hide archived' : 'Show archived'}
                    </Link>
                </Button>

                {archivedRoutines === null ? null : archivedRoutines.length ===
                  0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        No archived Routines.
                    </p>
                ) : (
                    <RoutineCards routines={archivedRoutines} />
                )}
            </div>
        </>
    );
}

/** Routine cards with Start, Edit and Last Done, or Restore for an Archived Routine. */
function RoutineCards({ routines }: { routines: Routine[] }) {
    const { auth } = usePage().props;

    return (
        <div className="flex flex-col gap-3">
            {routines.map((routine) => (
                <RoutineCard
                    key={routine.id}
                    name={routine.name}
                    exerciseCount={routine.exercises.length}
                    setCount={routine.exercises.reduce(
                        (total, planned) => total + planned.sets.length,
                        0,
                    )}
                    muscles={primaryMuscles(routine)}
                    lastDone={
                        routine.last_done === undefined
                            ? undefined
                            : formatLastDone(
                                  routine.last_done,
                                  auth.user.timezone,
                              )
                    }
                    archived={routine.archived}
                    onStart={() =>
                        router.visit(RoutineController.start(routine.id))
                    }
                    onEdit={() =>
                        router.visit(RoutineController.edit(routine.id))
                    }
                    onRestore={() =>
                        router.visit(RoutineController.restore(routine.id), {
                            preserveScroll: true,
                        })
                    }
                />
            ))}
        </div>
    );
}

/** Primary Muscles the Routine trains, the most planned working Sets first. */
function primaryMuscles(routine: Routine): string[] {
    const workingSets = new Map<string, number>();

    for (const planned of routine.exercises) {
        const count = planned.sets.filter((set) => !set.is_warm_up).length;

        for (const trained of planned.exercise.muscles) {
            if (trained.role === 'primary') {
                workingSets.set(
                    trained.muscle,
                    (workingSets.get(trained.muscle) ?? 0) + count,
                );
            }
        }
    }

    return [...workingSets.entries()]
        .sort(([, a], [, b]) => b - a)
        .map(([muscle]) => muscle);
}

/** The sign in front of the % change, by its trend. */
const signs = { up: '+', down: '−', flat: '' } as const;

/** This Week's Workouts and Volume, with the % change against last Week up to the same moment while last Week had Volume by then. */
function ThisWeekStrip({ thisWeek }: { thisWeek: ThisWeek }) {
    const { workouts, volume, last_week_volume: lastWeekVolume } = thisWeek;
    const change =
        lastWeekVolume > 0
            ? Math.round(((volume - lastWeekVolume) / lastWeekVolume) * 100)
            : null;
    const trend =
        change === null || change === 0 ? 'flat' : change > 0 ? 'up' : 'down';

    return (
        <section
            aria-label="This week"
            className="grid grid-cols-2 gap-4 rounded-xl border bg-card p-4 text-card-foreground"
        >
            <StatBlock
                size="sm"
                tone="plain"
                value={workouts}
                unit={workouts === 1 ? 'Workout' : 'Workouts'}
                label="This week"
            />
            <StatBlock
                size="sm"
                value={formatKg(volume)}
                unit="kg"
                label="Volume"
                delta={
                    change === null ? undefined : (
                        <>
                            {signs[trend]}
                            {Math.abs(change)}%
                            <span className="sr-only">
                                {' '}
                                against last week up to now
                            </span>
                        </>
                    )
                }
                trend={trend}
            />
        </section>
    );
}

/** New routine and the settings gear, beside the title. */
function HomeActions() {
    return (
        <>
            <Button asChild size="sm">
                <Link href={RoutineController.create()}>
                    <PlusIcon aria-hidden="true" />
                    New routine
                </Link>
            </Button>
            <Button asChild variant="ghost" size="icon" className="size-11">
                <Link href={editProfile()} aria-label="Settings">
                    <SettingsIcon aria-hidden="true" className="size-5" />
                </Link>
            </Button>
        </>
    );
}

Home.layout = {
    tab: 'routines',
    title: 'Routines',
    actions: <HomeActions />,
} satisfies PageShell;
