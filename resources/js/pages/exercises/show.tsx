import { Head, Link, usePage } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import { PageContent } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { repeat, SkeletonBlock } from '@/components/page-skeleton';
import { HistoryEntry } from '@/components/statsheet/history-entry';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { PersonalRecordsCard } from '@/components/statsheet/personal-records';
import { TrendChart } from '@/components/statsheet/trend-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { parentScreens } from '@/lib/parent-screens';
import { formatDay, formatKg } from '@/lib/utils';
import type {
    Exercise,
    ExercisePerformance,
    ExerciseProgressPoint,
    PageShell,
    PersonalRecords,
} from '@/types';

export default function ShowExercise({
    exercise,
    records,
    progress,
    recent,
}: {
    /** Every prop is undefined while an instant visit loads the page. */
    exercise?: Exercise;
    records?: PersonalRecords;
    /** One point per Workout with a qualifying Set, oldest first. */
    progress?: ExerciseProgressPoint[];
    /** The most recent Workouts, newest first. */
    recent?: ExercisePerformance[];
}) {
    const { auth } = usePage().props;
    const timeZone = auth.user.timezone;

    if (
        exercise === undefined ||
        records === undefined ||
        progress === undefined ||
        recent === undefined
    ) {
        return <ExerciseSkeleton />;
    }

    const bodyweight = exercise.is_bodyweight;

    return (
        <>
            <Head title={exercise.name} />

            <PageContent
                loading={false}
                className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4"
            >
                <div className="flex items-start justify-between gap-3">
                    <PageDescription>
                        {bodyweight
                            ? 'Heaviest and reps-at-weight use the added load. Estimated 1RM and best set add your Bodyweight.'
                            : 'Personal Records count done Sets, never warm-ups.'}
                    </PageDescription>
                    {exercise.archived ? (
                        <Badge variant="outline">Archived</Badge>
                    ) : (
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={ExerciseController.edit(exercise.id)}
                                component="exercises/edit"
                            >
                                <PencilIcon aria-hidden="true" />
                                Edit
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="-mt-4 flex flex-wrap gap-1.5">
                    {exercise.muscles.map((trained) => (
                        <MuscleTag
                            key={trained.muscle}
                            muscle={trained.muscle}
                            role={trained.role}
                        />
                    ))}
                </div>

                <PersonalRecordsCard
                    records={records}
                    bodyweight={bodyweight}
                />

                {progress.length ? (
                    <section className="flex flex-col gap-6 rounded-xl border bg-card p-4 text-card-foreground">
                        <TrendChart
                            title="Best Est. 1RM per Workout"
                            unit="kg"
                            points={progress
                                .filter((point) => point.e1rm !== null)
                                .map((point) => ({
                                    label: formatDay(
                                        point.started_at,
                                        timeZone,
                                    ),
                                    value: point.e1rm!,
                                }))}
                        />
                        <TrendChart
                            title={
                                bodyweight
                                    ? 'Heaviest added load per Workout'
                                    : 'Heaviest weight per Workout'
                            }
                            unit="kg"
                            points={progress.map((point) => ({
                                label: formatDay(point.started_at, timeZone),
                                value: point.heaviest,
                            }))}
                        />
                    </section>
                ) : null}

                {recent.length ? (
                    <section aria-labelledby="recent">
                        <h2 id="recent" className="text-base font-medium">
                            Recent performances
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            % is the heaviest set's Intensity: its weight as a
                            share of your Expected 1RM, your best earlier
                            Estimated 1RM lowered 1% a week after 3 weeks (at
                            most 15%). Avg is the Average Weight per rep.
                            {bodyweight ? ' Both add your Bodyweight.' : null}
                        </p>
                        {recent.map((performance) => (
                            <HistoryEntry
                                key={performance.workout_id}
                                date={formatDay(
                                    performance.started_at,
                                    timeZone,
                                    { weekday: true },
                                )}
                                workout={performance.routine ?? 'Empty workout'}
                                e1rm={
                                    performance.e1rm === null
                                        ? undefined
                                        : formatKg(performance.e1rm)
                                }
                                intensity={
                                    performance.intensity === null
                                        ? undefined
                                        : String(performance.intensity)
                                }
                                averageWeight={
                                    performance.average_weight === null
                                        ? undefined
                                        : formatKg(performance.average_weight)
                                }
                                bodyweight={bodyweight}
                                prs={performance.new_records}
                                sets={performance.sets.map((set) => ({
                                    reps: set.reps,
                                    weight: set.weight,
                                    kind: set.kind,
                                    top: set.top,
                                }))}
                            />
                        ))}
                    </section>
                ) : null}
            </PageContent>
        </>
    );
}

/** The Exercise detail while its props load: real section headings and labels, grey Muscles, records, charts and performances. */
function ExerciseSkeleton() {
    return (
        <>
            <Head title="Exercise" />

            <PageContent
                loading
                className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4"
            >
                <div className="flex items-start justify-between gap-3">
                    <SkeletonBlock className="my-0.5 h-4 w-2/3" />
                    <SkeletonBlock className="h-8 w-16 rounded-full" />
                </div>

                <div className="-mt-4 flex flex-wrap gap-1.5">
                    {repeat(3, (index) => (
                        <SkeletonBlock
                            key={index}
                            className="h-6 w-16 rounded-full"
                        />
                    ))}
                </div>

                <PersonalRecordsCard />

                <section className="flex flex-col gap-6 rounded-xl border bg-card p-4 text-card-foreground">
                    {repeat(2, (index) => (
                        <div key={index} className="flex flex-col gap-2">
                            <SkeletonBlock className="h-4 w-40" />
                            <SkeletonBlock className="h-[200px]" />
                        </div>
                    ))}
                </section>

                <section aria-labelledby="recent">
                    <h2 id="recent" className="text-base font-medium">
                        Recent performances
                    </h2>
                    {repeat(3, (index) => (
                        <div
                            key={index}
                            className="flex flex-col gap-2 border-b py-4 last:border-b-0"
                        >
                            <SkeletonBlock className="h-5 w-1/2" />
                            <div className="flex flex-wrap gap-1.5">
                                {repeat(3, (set) => (
                                    <SkeletonBlock
                                        key={set}
                                        className="h-7 w-20 rounded-full"
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </section>
            </PageContent>
        </>
    );
}

/** The title is the Exercise's name: a grey block, read as "Exercise", while it loads. */
ShowExercise.layout = ({ exercise }: { exercise?: Exercise }): PageShell => ({
    tab: 'stats',
    title: exercise?.name,
    loadingTitle: 'Exercise',
    parent: parentScreens.exercises,
});
