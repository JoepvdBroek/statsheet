import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
};

/** One of the bottom tab bar's sections. */
export type AppTab = 'routines' | 'workout' | 'stats';

/** The fixed screen a detail screen's back chevron goes to. */
export type ParentScreen = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** The screen's page component, for the instant visit back to it. */
    component: string;
};

/** What every app page declares for the layout, as its layout props. */
export type PageShell = {
    /** The tab to highlight; null for screens outside the tabs, such as settings. */
    tab: AppTab | null;
    /** Shown large on a tab screen, beside a back chevron on a detail screen. Undefined while it comes from props still loading. */
    title: string | undefined;
    /** For a title that comes from props: read to screen readers while they load, with a grey block shown instead. */
    loadingTitle?: string;
    /** Only on detail screens. */
    parent?: ParentScreen;
    /** Buttons beside the title, such as New routine and the settings gear on home. */
    actions?: ReactNode;
};
