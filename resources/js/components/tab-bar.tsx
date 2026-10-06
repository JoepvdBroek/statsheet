import { Link, usePage } from '@inertiajs/react';
import { ChartLine, ClipboardList, Dumbbell } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { index as routinesIndex } from '@/routes/routines';
import { hub as statsHub } from '@/routes/statistics';
import { index as workoutsIndex, show as showWorkout } from '@/routes/workouts';
import type { AppTab, NavItem } from '@/types';

type Tab = NavItem & { tab: AppTab; icon: LucideIcon; live?: boolean };

/**
 * The bottom tab bar on every screen width. The Workout tab opens the Workout in progress, with a neon dot, or else Workout history.
 */
export function TabBar({ activeTab }: { activeTab: AppTab | null }) {
    const { workoutInProgressId } = usePage().props;

    const tabs: Tab[] = [
        {
            tab: 'routines',
            title: 'Routines',
            href: routinesIndex(),
            icon: ClipboardList,
        },
        {
            tab: 'workout',
            title: 'Workout',
            href:
                workoutInProgressId === null
                    ? workoutsIndex()
                    : showWorkout(workoutInProgressId),
            icon: Dumbbell,
            live: workoutInProgressId !== null,
        },
        { tab: 'stats', title: 'Stats', href: statsHub(), icon: ChartLine },
    ];

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
                            prefetch
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
