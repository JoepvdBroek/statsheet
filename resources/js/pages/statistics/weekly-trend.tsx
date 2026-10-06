import { Head, router } from '@inertiajs/react';
import { nativeSelectClassName } from '@/components/exercise-form';
import { PageDescription } from '@/components/page-description';
import { TrendChart } from '@/components/statsheet/trend-chart';
import { parentScreens } from '@/lib/parent-screens';
import { capitalize, formatDate } from '@/lib/utils';
import { weeklyTrend } from '@/routes/statistics';
import type { PageShell } from '@/types';

type TrendWeek = {
    /** The Week's Monday, as a date. */
    starts_on: string;
    /** kg; 0 in a Week without Workouts. */
    volume: number;
    /** The weekly minimum in kg of the Goal in force that Week, if any. */
    goal: number | null;
};

export default function WeeklyTrend({
    muscle,
    muscles,
    weeks,
}: {
    /** The Muscle shown. */
    muscle: string;
    /** Every Muscle, to choose from. */
    muscles: string[];
    /** One per Week, oldest first, ending with this Week. */
    weeks: TrendWeek[];
}) {
    return (
        <>
            <Head title="Weekly trend" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <PageDescription>
                        Volume per Week against the Goal in force that Week,
                        this Week included.
                    </PageDescription>
                    <select
                        aria-label="Muscle"
                        className={nativeSelectClassName}
                        value={muscle}
                        onChange={(event) =>
                            router.get(
                                weeklyTrend.url(),
                                { muscle: event.target.value },
                                { preserveScroll: true, replace: true },
                            )
                        }
                    >
                        {muscles.map((option) => (
                            <option key={option} value={option}>
                                {capitalize(option)}
                            </option>
                        ))}
                    </select>
                </div>

                <section className="rounded-xl border bg-card p-4 text-card-foreground">
                    <TrendChart
                        title={`${capitalize(muscle)} Volume per Week`}
                        unit="kg"
                        points={weeks.map((week) => ({
                            label: formatDate(week.starts_on),
                            value: week.volume,
                            goal: week.goal,
                        }))}
                    />
                </section>
            </div>
        </>
    );
}

WeeklyTrend.layout = {
    tab: 'stats',
    title: 'Weekly trend',
    parent: parentScreens.stats,
} satisfies PageShell;
