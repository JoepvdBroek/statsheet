import { PageHeader } from '@/components/page-header';
import { TabBar } from '@/components/tab-bar';
import type { AppLayoutProps } from '@/types';

/**
 * The app's one layout on every screen width: a page header, a centred column of about phone width, and the bottom tab bar.
 * Each page declares its tab, title and, on a detail screen, its parent as layout props.
 */
export default function AppLayout({
    tab = null,
    title,
    parent,
    children,
}: AppLayoutProps) {
    return (
        <div className="min-h-svh bg-background">
            <main className="mx-auto flex w-full max-w-160 flex-col pb-(--tab-bar-height)">
                <PageHeader title={title} parent={parent} />
                {children}
            </main>
            <TabBar activeTab={tab} />
        </div>
    );
}
