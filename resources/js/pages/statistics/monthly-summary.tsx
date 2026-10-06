import { Head, Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import Heading from '@/components/heading';
import { StatBlock } from '@/components/statsheet/stat-block';
import { Button } from '@/components/ui/button';
import { parentScreens } from '@/lib/parent-screens';
import { capitalize, formatKg, formatMonth } from '@/lib/utils';
import { monthlySummary } from '@/routes/statistics';
import type { PageShell } from '@/types';

type MuscleVolume = {
    muscle: string;
    /** kg */
    volume: number;
};

type GoalWeeks = {
    muscle: string;
    /** Weeks whose Volume reached the Goal in force then. */
    weeks_met: number;
    /** Weeks with this Muscle's Goal in force. */
    weeks_with_goal: number;
};

export default function MonthlySummary({
    month,
    previousMonth,
    nextMonth,
    workouts,
    volume,
    goals,
}: {
    /** The month shown, as "2026-09". */
    month: string;
    /** The month before, as "2026-08". */
    previousMonth: string;
    /** Empty for this month: later months have nothing to show yet. */
    nextMonth: string | null;
    /** Workouts started in the month, in progress or not. */
    workouts: number;
    /** Each Muscle with Volume in the month, most Volume first. */
    volume: MuscleVolume[];
    /** Each Muscle with a Goal in force in one of the month's Weeks so far, by name. A Week belongs to the month of its Monday. */
    goals: GoalWeeks[];
}) {
    return (
        <>
            <Head title={`Monthly summary · ${formatMonth(month)}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={formatMonth(month)}
                        description="Volume, Workouts and Goals met in one calendar month."
                    />
                    <nav
                        aria-label="Months"
                        className="flex items-center gap-2"
                    >
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={monthlySummary({
                                    query: { month: previousMonth },
                                })}
                            >
                                <ChevronLeftIcon aria-hidden="true" />
                                {formatMonth(previousMonth)}
                            </Link>
                        </Button>
                        {nextMonth ? (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={monthlySummary({
                                        query: { month: nextMonth },
                                    })}
                                >
                                    {formatMonth(nextMonth)}
                                    <ChevronRightIcon aria-hidden="true" />
                                </Link>
                            </Button>
                        ) : null}
                    </nav>
                </div>

                <section className="rounded-xl border bg-card p-4 text-card-foreground">
                    <StatBlock
                        value={workouts}
                        unit={workouts === 1 ? 'Workout' : 'Workouts'}
                        label={`Started in ${formatMonth(month)}`}
                    />
                </section>

                <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
                    <div className="space-y-0.5">
                        <h2 className="text-base font-medium">Goals met</h2>
                        <p className="text-sm text-muted-foreground">
                            Weeks starting this month in which each Goal was
                            met, judged against the Goal in force that Week.
                        </p>
                    </div>

                    {goals.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            No Goal was in force in a Week starting this month
                            so far.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                            {goals.map(
                                ({ muscle, weeks_met, weeks_with_goal }) => (
                                    <StatBlock
                                        key={muscle}
                                        size="sm"
                                        tone="plain"
                                        value={`${weeks_met} / ${weeks_with_goal}`}
                                        unit="Weeks"
                                        label={capitalize(muscle)}
                                    />
                                ),
                            )}
                        </div>
                    )}
                </section>

                <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
                    <div className="space-y-0.5">
                        <h2 className="text-base font-medium">
                            Volume per Muscle
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            From the Workouts started this month.
                        </p>
                    </div>

                    {volume.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            No Volume this month.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                            {volume.map(({ muscle, volume: tonnage }) => (
                                <StatBlock
                                    key={muscle}
                                    size="sm"
                                    tone="plain"
                                    value={formatKg(tonnage)}
                                    unit="kg"
                                    label={capitalize(muscle)}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

MonthlySummary.layout = {
    tab: 'stats',
    title: 'Monthly summary',
    parent: parentScreens.stats,
} satisfies PageShell;
