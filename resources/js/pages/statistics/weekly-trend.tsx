import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { nativeSelectClassName } from '@/components/exercise-form';
import { PageContent } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { SkeletonBlock } from '@/components/page-skeleton';
import { TrendChart } from '@/components/statsheet/trend-chart';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { UpdatingContent, UpdatingSelect } from '@/components/updating-content';
import { useSamePageReload } from '@/hooks/use-same-page-reload';
import { parentScreens } from '@/lib/parent-screens';
import {
    capitalize,
    formatDate,
    formatSetCount,
    formatSets,
} from '@/lib/utils';
import { weeklyTrend } from '@/routes/statistics';
import type { MuscleSetCount, PageShell } from '@/types';

/** The Muscle's Week. Volume, Set Count and Direct Sets are 0 in a Week without Workouts. */
type TrendWeek = MuscleSetCount & {
    /** The Week's Monday, as a date. */
    starts_on: string;
    /** kg */
    volume: number;
    /** The weekly minimum in kg of the Goal in force that Week, if any. */
    goal: number | null;
};

/** What the chart shows per Week. */
type Measure = 'volume' | 'set_count';

/** Set Counts come in halves. */
const setCountStep = 0.5;

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
    const [measure, setMeasure] = useState<Measure>('volume');

    if (muscle === undefined || muscles === undefined || weeks === undefined) {
        return (
            <WeeklyTrendContent
                loading
                measure={measure}
                onMeasureChange={setMeasure}
                muscleSelect={<SkeletonBlock className="h-9" />}
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
            measure={measure}
            onMeasureChange={setMeasure}
            muscleSelect={
                <UpdatingSelect updating={updatingField === 'muscle'}>
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
                    {measure === 'volume' ? (
                        <TrendChart
                            title={`${capitalize(muscle)} Volume per Week`}
                            unit="kg"
                            points={weeks.map((week) => ({
                                label: formatDate(week.starts_on),
                                value: week.volume,
                                goal: week.goal,
                            }))}
                        />
                    ) : (
                        <TrendChart
                            title={`${capitalize(muscle)} Set Count per Week`}
                            formatValue={formatSetCount}
                            formatWithUnit={formatSets}
                            minTickStep={setCountStep}
                            points={weeks.map((week) => ({
                                label: formatDate(week.starts_on),
                                value: week.set_count,
                            }))}
                        />
                    )}
                </UpdatingContent>
            }
        />
    );
}

/** The page around its Muscle select and chart, which are grey while the page loads. The kg / Sets switch works from the start. */
function WeeklyTrendContent({
    loading,
    measure,
    onMeasureChange,
    muscleSelect,
    chart,
}: {
    loading: boolean;
    measure: Measure;
    onMeasureChange: (measure: Measure) => void;
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
                        Volume or Set Count per Week, this Week included. The
                        Goal in force each Week is about Volume.
                    </PageDescription>
                    <div className="flex shrink-0 items-center gap-3">
                        <div className="min-w-0 flex-1 sm:w-40 sm:flex-none">
                            {muscleSelect}
                        </div>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-label="Show Volume or Set Count"
                            className="shrink-0"
                            value={measure}
                            onValueChange={(value) => {
                                // Pressing the selected item gives an empty value: one always stays selected.
                                if (
                                    value === 'volume' ||
                                    value === 'set_count'
                                ) {
                                    onMeasureChange(value);
                                }
                            }}
                        >
                            <ToggleGroupItem value="volume">kg</ToggleGroupItem>
                            <ToggleGroupItem value="set_count">
                                Sets
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
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
