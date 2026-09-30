import { Head, Link, usePage } from '@inertiajs/react';
import { PencilIcon } from 'lucide-react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import Heading from '@/components/heading';
import { HistoryEntry } from '@/components/statsheet/history-entry';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { PRBadge } from '@/components/statsheet/pr-badge';
import { StatBlock } from '@/components/statsheet/stat-block';
import { TrendChart } from '@/components/statsheet/trend-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDay } from '@/lib/utils';
import { index } from '@/routes/exercises';
import type {
    Exercise,
    ExercisePerformance,
    ExerciseProgressPoint,
    PersonalRecords,
} from '@/types';

const kgFormat = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 1,
});

export default function ShowExercise({
    exercise,
    records,
    progress,
    recent,
}: {
    exercise: Exercise;
    records: PersonalRecords;
    /** One point per Workout with a qualifying Set, oldest first. */
    progress: ExerciseProgressPoint[];
    /** The most recent Workouts, newest first. */
    recent: ExercisePerformance[];
}) {
    const { auth } = usePage().props;
    const timeZone = auth.user.timezone;
    const bodyweight = exercise.is_bodyweight;
    const addedLoad = (weight: number) =>
        bodyweight
            ? `BW${weight ? `+${kgFormat.format(weight)}` : ''}`
            : kgFormat.format(weight);

    return (
        <>
            <Head title={exercise.name} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-3">
                    <Heading
                        title={exercise.name}
                        description={
                            bodyweight
                                ? 'Heaviest and reps-at-weight use the added load. Estimated 1RM and best set add your Bodyweight.'
                                : 'Personal Records count done Sets, never warm-ups.'
                        }
                    />
                    {exercise.archived ? (
                        <Badge variant="outline">Archived</Badge>
                    ) : (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ExerciseController.edit(exercise.id)}>
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

                <section
                    aria-labelledby="records"
                    className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground"
                >
                    <h2
                        id="records"
                        className="flex items-center gap-2 text-base font-medium"
                    >
                        <PRBadge />
                        Personal Records
                    </h2>
                    {records.heaviest === null ? (
                        <p className="text-sm text-muted-foreground">
                            No done working Sets yet. Log one to set your first
                            records.
                        </p>
                    ) : (
                        <>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                <StatBlock
                                    size="sm"
                                    tone="plain"
                                    label="Heaviest"
                                    value={addedLoad(records.heaviest)}
                                    unit={
                                        bodyweight && !records.heaviest
                                            ? undefined
                                            : 'kg'
                                    }
                                />
                                <StatBlock
                                    size="sm"
                                    tone="plain"
                                    label="Est. 1RM"
                                    value={
                                        records.e1rm === null
                                            ? '–'
                                            : kgFormat.format(records.e1rm)
                                    }
                                    unit={
                                        records.e1rm === null ? undefined : 'kg'
                                    }
                                />
                                <StatBlock
                                    size="sm"
                                    tone="plain"
                                    label="Best set"
                                    value={kgFormat.format(
                                        records.tonnage ?? 0,
                                    )}
                                    unit="kg"
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <h3 className="text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase">
                                    Most reps at a weight
                                </h3>
                                <ul className="flex flex-wrap gap-1.5">
                                    {records.reps_at_weight.map((best) => (
                                        <li
                                            key={best.weight}
                                            className="inline-flex h-7 items-center rounded-full bg-secondary px-2.5 text-sm tabular-nums"
                                        >
                                            {best.reps} ×{' '}
                                            {addedLoad(best.weight)}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </>
                    )}
                </section>

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
                                        : kgFormat.format(performance.e1rm)
                                }
                                bodyweight={bodyweight}
                                prs={performance.new_records}
                                sets={performance.sets.map((set) => ({
                                    reps: set.reps,
                                    weight: set.weight,
                                    warmup: set.warm_up,
                                    top: set.top,
                                }))}
                            />
                        ))}
                    </section>
                ) : null}
            </div>
        </>
    );
}

ShowExercise.layout = {
    breadcrumbs: [
        {
            title: 'Exercises',
            href: index(),
        },
    ],
};
