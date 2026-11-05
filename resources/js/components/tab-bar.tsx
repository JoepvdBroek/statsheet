import { Link, usePage } from '@inertiajs/react';
import { ChartLine, ClipboardList, Dumbbell } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useInstantVisitComponent } from '@/hooks/use-instant-visit-component';
import { usePrefetchOnPageChange } from '@/hooks/use-prefetch-on-page-change';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { hub as statsHub } from '@/routes/statistics';
import { index as workoutsIndex, show as showWorkout } from '@/routes/workouts';
import type { AppTab, NavItem, PageComponent } from '@/types';

type Tab = NavItem & {
    tab: AppTab;
    /** The tab's page component, for the instant visit to it. */
    component: PageComponent;
    icon: LucideIcon;
    live?: boolean;
};

/**
 * The bottom tab bar on every screen width. The Workout tab opens the Workout in progress, with a neon dot, or else Workout history.
 * A tap opens the tab's page at once, as an instant visit, unless it's the page you're on.
 * The tabs you're not on are prefetched after every page change.
 */
export function TabBar({ activeTab }: { activeTab: AppTab | null }) {
    const { workoutInProgressId } = usePage().props;
    const instantVisitComponent = useInstantVisitComponent();

    const tabs: Tab[] = [
        {
            tab: 'routines',
            title: 'Routines',
            href: home(),
            component: 'home',
            icon: ClipboardList,
        },
        {
            tab: 'workout',
            title: 'Workout',
            href:
                workoutInProgressId === null
                    ? workoutsIndex()
                    : showWorkout(workoutInProgressId),
            component:
                workoutInProgressId === null
                    ? 'workouts/index'
                    : 'workouts/show',
            icon: Dumbbell,
            live: workoutInProgressId !== null,
        },
        {
            tab: 'stats',
            title: 'Stats',
            href: statsHub(),
            component: 'statistics/hub',
            icon: ChartLine,
        },
    ];

    usePrefetchOnPageChange(
        tabs.filter((item) => item.tab !== activeTab).map((item) => item.href),
    );

    return (
        <nav
            aria-label="Main"
            className="fixed inset-x-0 bottom-0 z-40 h-(--tab-bar-height) border-t bg-card pb-[env(safe-area-inset-bottom)]"
        >
            <div className="mx-auto flex h-16 max-w-160 px-2 py-1">
                {tabs.map((item) => {
                    const active = item.tab === activeTab;

                    return (
                        <Link
                            key={item.tab}
                            href={item.href}
                            component={instantVisitComponent(
                                item.href,
                                item.component,
                            )}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'relative flex flex-1 basis-0 flex-col items-center justify-center gap-1 rounded-lg text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                active
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            <item.icon
                                aria-hidden="true"
                                className={cn(
                                    'size-[22px]',
                                    active && 'text-primary',
                                )}
                            />
                            {item.live ? (
                                <span
                                    aria-hidden="true"
                                    className="absolute top-2 ml-[22px] size-2 rounded-full bg-primary shadow-[0_0_0_2px_var(--card)]"
                                />
                            ) : null}
                            <span>
                                {item.title}
                                {item.live ? (
                                    <span className="sr-only">
                                        {' '}
                                        (in progress)
                                    </span>
                                ) : null}
                            </span>
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
