import { Head, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { nativeSelectClassName } from '@/components/exercise-form';
import { PageContent } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { SkeletonBlock } from '@/components/page-skeleton';
import { TrendChart } from '@/components/statsheet/trend-chart';
import { UpdatingContent, UpdatingSelect } from '@/components/updating-content';
import { useSamePageReload } from '@/hooks/use-same-page-reload';
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
    /** The Muscle shown. Every prop is undefined while an instant visit loads the page. */
    muscle?: string;
    /** Every Muscle, to choose from. */
    muscles?: string[];
    /** One per Week, oldest first, ending with this Week. */
    weeks?: TrendWeek[];
}) {
    const { updatingField, reloadOptions } = useSamePageReload<'muscle'>();

    if (muscle === undefined || muscles === undefined || weeks === undefined) {
        return (
            <WeeklyTrendContent
                loading
                muscleSelect={
                    <SkeletonBlock className="h-9 shrink-0 sm:w-40" />
                }
                chart={
                    <div className="flex flex-col gap-2">
                        <SkeletonBlock className="h-4 w-48" />
                        <SkeletonBlock className="h-[200px]" />
                    </div>
                }
            />
        );
    }

    return (
        <WeeklyTrendContent
            loading={false}
            muscleSelect={
                <UpdatingSelect
                    updating={updatingField === 'muscle'}
                    className="w-full"
                >
                    <select
                        aria-label="Muscle"
                        className={nativeSelectClassName}
                        value={muscle}
                        onChange={(event) =>
                            router.get(
                                weeklyTrend.url(),
                                { muscle: event.target.value },
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                    replace: true,
                                    ...reloadOptions('muscle'),
                                },
                            )
                        }
                    >
                        {muscles.map((option) => (
                            <option key={option} value={option}>
                                {capitalize(option)}
                            </option>
                        ))}
                    </select>
                </UpdatingSelect>
            }
            chart={
                <UpdatingContent updating={updatingField !== null}>
                    <TrendChart
                        title={`${capitalize(muscle)} Volume per Week`}
                        unit="kg"
                        points={weeks.map((week) => ({
                            label: formatDate(week.starts_on),
                            value: week.volume,
                            goal: week.goal,
                        }))}
                    />
                </UpdatingContent>
            }
        />
    );
}

/** The page around its Muscle select and chart, which are grey while the page loads. */
function WeeklyTrendContent({
    loading,
    muscleSelect,
    chart,
}: {
    loading: boolean;
    muscleSelect: ReactNode;
    chart: ReactNode;
}) {
    return (
        <>
            <Head title="Weekly trend" />

            <PageContent
                loading={loading}
                className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <PageDescription>
                        Volume per Week against the Goal in force that Week,
                        this Week included.
                    </PageDescription>
                    {muscleSelect}
                </div>

                <section className="rounded-xl border bg-card p-4 text-card-foreground">
                    {chart}
                </section>
            </PageContent>
        </>
    );
}

WeeklyTrend.layout = {
    tab: 'stats',
    title: 'Weekly trend',
    parent: parentScreens.stats,
} satisfies PageShell;
