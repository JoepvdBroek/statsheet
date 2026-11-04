import { PageHeader } from '@/components/page-header';
import {
    PrototypePageSkeleton,
    usePrototypeNavigation,
} from '@/components/prototype-navigation-feedback';
import { cn } from '@/lib/utils';
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
    actions,
    children,
}: AppLayoutProps) {
    const nav = usePrototypeNavigation();
    const waiting = nav.pending && nav.showWait ? nav.pending : null;
    const skeleton = nav.variant === 'C' && waiting?.kind === 'page';
    const reloading = waiting?.kind === 'reload';

    return (
        <div className="min-h-svh bg-background">
            <main className="mx-auto flex w-full max-w-160 flex-col pb-(--tab-bar-height)">
                {skeleton ? (
                    <PrototypePageSkeleton />
                ) : (
                    <>
                        <PageHeader
                            title={title}
                            parent={parent}
                            actions={actions}
                        />
                        <div
                            className={cn(
                                'transition-opacity',
                                reloading && 'pointer-events-none opacity-50',
                            )}
                            aria-busy={reloading || undefined}
                        >
                            {children}
                        </div>
                    </>
                )}
            </main>
            <TabBar activeTab={tab} />
        </div>
    );
}
