import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronRightIcon,
    Dumbbell,
    Goal,
    MessageSquareText,
    TrendingUp,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    AskForReviewForm,
    AskForReviewFormSkeleton,
} from '@/components/ask-for-review-form';
import { PageContent } from '@/components/page-content';
import { repeat, SkeletonBlock } from '@/components/page-skeleton';
import { StatBlock } from '@/components/statsheet/stat-block';
import { WeeklyReviewLink } from '@/components/weekly-review-link';
import { Button } from '@/components/ui/button';
import { capitalize, formatDate, formatKg } from '@/lib/utils';
import { index as exercisesIndex } from '@/routes/exercises';
import { index as goalsIndex } from '@/routes/goals';
import { index as reviewsIndex } from '@/routes/reviews';
import { monthlySummary, weeklyTrend } from '@/routes/statistics';
import type { NavItem, PageComponent, PageShell, WeeklyReview } from '@/types';

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

type HubRow = NavItem & {
    description: string;
    /** The view's page component, for the instant visit to it. */
    component: PageComponent;
    icon: LucideIcon;
};

/** The statistics views and their settings, each opened from a row on the hub. */
const rows: HubRow[] = [
    {
        title: 'Weekly trend',
        description: "A Muscle's Volume per Week against its Goal",
        href: weeklyTrend(),
        component: 'statistics/weekly-trend',
        icon: TrendingUp,
    },
    {
        title: 'Monthly summary',
        description: 'A month of Workouts, Volume and Goals met',
        href: monthlySummary(),
        component: 'statistics/monthly-summary',
        icon: CalendarDays,
    },
    {
        title: 'Exercises',
        description: "Each Exercise's progress and history",
        href: exercisesIndex(),
        component: 'exercises/index',
        icon: Dumbbell,
    },
    {
        title: 'Weekly Reviews',
        description: 'Earlier reviews and their advice',
        href: reviewsIndex(),
        component: 'reviews/index',
        icon: MessageSquareText,
    },
    {
        title: 'Goals',
        description: 'A weekly minimum Volume per Muscle',
        href: goalsIndex(),
        component: 'goals/index',
        icon: Goal,
    },
];

export default function StatsHub({
    thisWeek,
    latestReview,
}: {
    /** This Week's Volume per Muscle, live: Sets in the Workout in progress count too. Undefined while an instant visit loads the page. */
    thisWeek?: ThisWeek;
    /** The Weekly Review of the most recent Week that has one. Undefined while an instant visit loads the page. */
    latestReview?: WeeklyReview | null;
}) {
    return (
        <>
            <Head title="Stats" />
            <PageContent
                loading={thisWeek === undefined}
                className="flex flex-col gap-4 p-4"
            >
                <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
                    <div className="flex items-start justify-between gap-4">
                        <div className="space-y-0.5">
                            <h2 className="text-base font-medium">This Week</h2>
                            {thisWeek === undefined ? (
                                <SkeletonBlock className="my-0.5 h-4 w-56" />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Volume per Muscle since Monday{' '}
                                    {formatDate(thisWeek.starts_on)}, including
                                    the Workout in progress.
                                </p>
                            )}
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={goalsIndex()} component="goals/index">
                                Goals
                            </Link>
                        </Button>
                    </div>

                    {thisWeek === undefined ? (
                        <div className="grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3">
                            {repeat(6, (index) => (
                                <StatBlock
                                    key={index}
                                    size="sm"
                                    value={
                                        <SkeletonBlock className="my-1 h-6 w-20" />
                                    }
                                    label={
                                        <SkeletonBlock className="my-0.5 h-3 w-16" />
                                    }
                                />
                            ))}
                        </div>
                    ) : thisWeek.muscles.length === 0 ? (
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
                                        value={formatKg(volume)}
                                        unit="kg"
                                        label={capitalize(muscle)}
                                        delta={
                                            goal === null
                                                ? undefined
                                                : met
                                                  ? `Goal met · ${formatKg(goal)}`
                                                  : `${formatKg(goal - volume)} to go`
                                        }
                                    />
                                ),
                            )}
                        </div>
                    )}
                </section>

                <WeeklyReviewCard
                    latestReview={latestReview}
                    thisWeekStartsOn={thisWeek?.starts_on}
                />

                <nav aria-label="Statistics">
                    <ul className="divide-y overflow-hidden rounded-xl border bg-card text-card-foreground">
                        {rows.map((row) => (
                            <li key={row.title}>
                                <Link
                                    href={row.href}
                                    component={row.component}
                                    className="flex min-h-14 items-center gap-3 px-4 py-3 outline-none hover:bg-accent focus-visible:bg-accent"
                                >
                                    <row.icon
                                        aria-hidden="true"
                                        className="size-5 shrink-0 text-primary"
                                    />
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-sm font-medium">
                                            {row.title}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {row.description}
                                        </span>
                                    </span>
                                    <ChevronRightIcon
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-muted-foreground"
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>
            </PageContent>
        </>
    );
}

function WeeklyReviewCard({
    latestReview,
    thisWeekStartsOn,
}: {
    /** Undefined, like the Week, while the page loads. */
    latestReview?: WeeklyReview | null;
    thisWeekStartsOn?: string;
}) {
    return (
        <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground">
            <div className="flex items-start justify-between gap-4">
                <div className="space-y-0.5">
                    <h2 className="text-base font-medium">Weekly Review</h2>
                    <p className="text-sm text-muted-foreground">
                        An AI-written look at a Week of your log. It only reads
                        your data; it never changes it.
                    </p>
                </div>
                <Button asChild variant="outline" size="sm">
                    <Link href={reviewsIndex()} component="reviews/index">
                        All reviews
                    </Link>
                </Button>
            </div>

            {latestReview === undefined ? (
                <SkeletonBlock className="h-16 rounded-lg" />
            ) : latestReview ? (
                <WeeklyReviewLink review={latestReview} />
            ) : null}

            {thisWeekStartsOn === undefined ? (
                <AskForReviewFormSkeleton />
            ) : (
                <AskForReviewForm defaultDay={thisWeekStartsOn} />
            )}
        </section>
    );
}

StatsHub.layout = { tab: 'stats', title: 'Stats' } satisfies PageShell;
