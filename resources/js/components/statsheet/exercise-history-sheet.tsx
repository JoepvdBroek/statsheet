import { Link, usePage } from '@inertiajs/react';
import { CornerDownRightIcon, HistoryIcon } from 'lucide-react';
import * as React from 'react';

import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import { repeat, SkeletonBlock } from '@/components/page-skeleton';
import { PerformanceEntry } from '@/components/statsheet/history-entry';
import {
    formatLoad,
    PersonalRecordsCard,
} from '@/components/statsheet/personal-records';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDay } from '@/lib/utils';
import type { Exercise, ExerciseHistory } from '@/types';

type LastTimeHintProps = {
    /** Undefined while it loads: a grey line in its place. */
    history?: ExerciseHistory;
    bodyweight?: boolean;
};

/**
 * One line with the non-warm-up Sets of the Exercise's most recent earlier performance ("Last time: 8 × 80 · 8 × 80"),
 * a Drop Set marked as dropping from the Set before it. "First time" without an earlier performance. Truncated to fit.
 */
function LastTimeHint({ history, bodyweight = false }: LastTimeHintProps) {
    const { auth } = usePage().props;

    if (history === undefined) {
        return <SkeletonBlock className="my-0.5 h-4 w-1/2" />;
    }

    const last = history.recent[0];
    const counted = last?.sets.filter((set) => set.kind !== 'warm_up') ?? [];

    if (!counted.length) {
        return (
            <span data-slot="last-time-hint" className="block truncate">
                {last ? 'Last time: warm-ups only' : 'First time'}
            </span>
        );
    }

    return (
        <span
            data-slot="last-time-hint"
            className="block truncate"
            title={formatDay(last.started_at, auth.user.timezone, {
                weekday: true,
            })}
        >
            Last time:{' '}
            {counted.map((set, index) => (
                <React.Fragment key={index}>
                    {set.kind === 'drop' ? (
                        <CornerDownRightIcon
                            aria-label="Drop set"
                            className="mx-1 inline size-3 align-baseline"
                        />
                    ) : index > 0 ? (
                        ' · '
                    ) : null}
                    {set.reps} × {formatLoad(set.weight, bodyweight)}
                </React.Fragment>
            ))}
        </span>
    );
}

type ExerciseHistorySheetProps = {
    exercise: Exercise;
    /** Undefined while it loads, or when it isn't known yet (an Exercise just Swapped in). */
    history?: ExerciseHistory;
    /** Whether `history` is still loading rather than missing. */
    loading?: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * An Exercise's Personal Records and recent performances in a bottom sheet, opened by its caller,
 * so they can be checked during a Workout without leaving it. Links to the Exercise's full history.
 */
function ExerciseHistorySheet({
    exercise,
    history,
    loading = false,
    open,
    onOpenChange,
}: ExerciseHistorySheetProps) {
    const bodyweight = exercise.is_bodyweight;
    const recentId = React.useId();
    const opener = React.useRef<Element | null>(null);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                data-slot="exercise-history-sheet"
                // Opened without a SheetTrigger, so focus goes back to whatever opened it, such as the Exercise's name.
                onOpenAutoFocus={() => {
                    opener.current = document.activeElement;
                }}
                onCloseAutoFocus={(event) => {
                    if (opener.current instanceof HTMLElement) {
                        event.preventDefault();
                        opener.current.focus();
                    }
                }}
                className="mx-auto max-h-[85dvh] max-w-160 gap-0 rounded-t-xl pb-[env(safe-area-inset-bottom)]"
            >
                <SheetHeader className="pr-12">
                    <SheetTitle className="text-lg leading-[1.3]">
                        {exercise.name}
                    </SheetTitle>
                    <SheetDescription>
                        {bodyweight
                            ? 'Heaviest and reps-at-weight use the added load. Est. 1RM, best set, % and avg add your Bodyweight.'
                            : 'Personal Records count done Sets, never warm-ups.'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-2">
                    {history !== undefined || loading ? (
                        <PersonalRecordsCard
                            records={history?.records}
                            bodyweight={bodyweight}
                        />
                    ) : null}

                    <section aria-labelledby={recentId}>
                        <h2 id={recentId} className="text-base font-medium">
                            Recent performances
                        </h2>
                        {history === undefined && loading ? (
                            repeat(3, (index) => (
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
                            ))
                        ) : history?.recent.length ? (
                            <>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    % is the heaviest set's Intensity. Avg is
                                    the Average Weight per rep.
                                </p>
                                {history.recent.map((performance) => (
                                    <PerformanceEntry
                                        key={performance.workout_id}
                                        performance={performance}
                                        bodyweight={bodyweight}
                                    />
                                ))}
                            </>
                        ) : (
                            <p className="mt-2 rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                {history === undefined
                                    ? 'No history to show yet.'
                                    : 'No earlier Workouts with this Exercise yet.'}
                            </p>
                        )}
                    </section>
                </div>

                <SheetFooter className="border-t">
                    <Button variant="outline" asChild>
                        <Link
                            href={ExerciseController.show(exercise.id)}
                            component="exercises/show"
                        >
                            <HistoryIcon aria-hidden="true" />
                            Full history
                        </Link>
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}

export { ExerciseHistorySheet, LastTimeHint };
export type { ExerciseHistorySheetProps, LastTimeHintProps };
