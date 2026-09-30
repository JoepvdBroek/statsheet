import { Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ChartLine,
    ClipboardList,
    Dumbbell,
    FolderGit2,
    History,
    LayoutGrid,
    NotebookText,
    Target,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as exercises } from '@/routes/exercises';
import { index as goals } from '@/routes/goals';
import { index as reviews } from '@/routes/reviews';
import { index as routines } from '@/routes/routines';
import { monthlySummary, weeklyTrend } from '@/routes/statistics';
import { index as workouts } from '@/routes/workouts';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Workouts',
        href: workouts(),
        icon: History,
    },
    {
        title: 'Routines',
        href: routines(),
        icon: ClipboardList,
    },
    {
        title: 'Goals',
        href: goals(),
        icon: Target,
    },
    {
        title: 'Weekly trend',
        href: weeklyTrend(),
        icon: ChartLine,
    },
    {
        title: 'Monthly summary',
        href: monthlySummary(),
        icon: CalendarDays,
    },
    {
        title: 'Weekly Reviews',
        href: reviews(),
        icon: NotebookText,
    },
    {
        title: 'Exercises',
        href: exercises(),
        icon: Dumbbell,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
