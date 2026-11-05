import { usePage } from '@inertiajs/react';
import { RotateCwIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { QuietSpinner } from '@/components/quiet-spinner';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageLoad } from '@/hooks/use-page-load';
import { loadPage } from '@/lib/page-load';
import type { PageFailure } from '@/lib/page-load';
import { cn } from '@/lib/utils';

/**
 * A page's content under its header. A page opened by an instant visit draws itself before its props arrive:
 * static headings, labels and buttons are real, and each data spot is a SkeletonBlock.
 * While `loading`, the content is aria-busy, so screen readers wait for the real content.
 *
 * When the page's load fails (see lib/failed-visits), the error state replaces the content; the header and tab bar
 * stay. When it runs over 8 s, a "Still loading" hint shows under it, announced politely.
 */
export function PageContent({
    loading,
    className,
    children,
}: {
    /** Whether the page's own props are still missing. */
    loading: boolean;
    className?: string;
    children: ReactNode;
}) {
    const { stillLoading, failure } = usePageLoad();

    if (failure !== null) {
        return (
            <div className={className}>
                <PageError failure={failure} />
            </div>
        );
    }

    return (
        <>
            <div aria-busy={loading || undefined} className={className}>
                {children}
            </div>
            <StillLoadingHint shown={stillLoading} />
        </>
    );
}

/** What the error state says for each failure, and whether Retry can help. */
function describeFailure(failure: PageFailure): {
    message: string;
    canRetry: boolean;
} {
    if (failure.kind === 'network') {
        return { message: "Couldn't load this page", canRetry: true };
    }

    switch (failure.status) {
        case 404:
            return { message: "This page doesn't exist", canRetry: false };
        case 403:
            return { message: "This isn't yours", canRetry: false };
        default:
            return { message: 'Something went wrong', canRetry: true };
    }
}

/**
 * The in-page error state of a page whose props couldn't load, in place of its content. It's announced
 * assertively, and focus moves to Retry, which loads the page again.
 */
export function PageError({ failure }: { failure: PageFailure }) {
    const page = usePage();
    const retryButton = useRef<HTMLButtonElement>(null);
    const { message, canRetry } = describeFailure(failure);

    useEffect(() => {
        retryButton.current?.focus();
    }, []);

    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed p-8 text-center">
            <p role="alert" className="text-sm text-muted-foreground">
                {message}
            </p>
            {canRetry ? (
                <Button
                    ref={retryButton}
                    variant="outline"
                    onClick={() => loadPage(page)}
                >
                    <RotateCwIcon aria-hidden="true" />
                    Retry
                </Button>
            ) : null}
        </div>
    );
}

/**
 * "Still loading — slow connection" with Retry, under a page whose props take over 8 s. The request keeps running;
 * Retry starts it again. The live region is always there, outside the aria-busy content, so the hint is announced.
 */
function StillLoadingHint({ shown }: { shown: boolean }) {
    const page = usePage();

    return (
        <div
            className={cn(
                'flex items-center justify-center gap-2 px-4 pb-4 text-sm text-muted-foreground',
                !shown && 'sr-only',
            )}
        >
            <p role="status">
                {shown ? 'Still loading — slow connection' : null}
            </p>
            {shown ? (
                <Button variant="link" size="sm" onClick={() => loadPage(page)}>
                    Retry
                </Button>
            ) : null}
        </div>
    );
}

/**
 * Content a same-page reload refreshes, such as a list under its search or a chart under its select. While
 * `updating`, from 150 ms into the reload (see usePageReload), it dims, can't be pressed and is aria-busy.
 */
export function UpdatingContent({
    updating,
    className,
    children,
}: {
    updating: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            aria-busy={updating || undefined}
            className={cn(
                'transition-opacity',
                updating && 'pointer-events-none opacity-50',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** A select a same-page reload was started from, with a spinner inside it while the reload is `updating`. */
export function UpdatingSelect({
    updating,
    className,
    children,
}: {
    updating: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('relative', className)}>
            {children}
            {updating ? (
                <QuietSpinner className="pointer-events-none absolute top-1/2 right-8 -translate-y-1/2 text-muted-foreground" />
            ) : null}
        </div>
    );
}

/**
 * A grey block standing in for one data spot, such as a name, value, date, input or chart, while the page's props load.
 * It shows 150 ms after it mounts, so a quick load never flashes grey, then pulses.
 * With reduced motion it shows at 150 ms without fading or pulsing. Hidden from screen readers.
 */
export function SkeletonBlock({ className }: { className?: string }) {
    return (
        <Skeleton
            aria-hidden="true"
            className={cn(
                'animate-skeleton-in motion-reduce:animate-skeleton-appear',
                className,
            )}
        />
    );
}

/**
 * A button's look and label without its action, for a button whose action needs the page's props while they load.
 * It can't be focused or pressed until the real button replaces it.
 */
export function InertButton({
    variant,
    size,
    className,
    children,
}: Pick<
    ComponentProps<typeof Button>,
    'variant' | 'size' | 'className' | 'children'
>) {
    return (
        <Button asChild variant={variant} size={size} className={className}>
            <span>{children}</span>
        </Button>
    );
}

/** A fixed typical number of skeleton items, such as 3 cards or 6 list rows: real counts aren't known yet. */
export function repeat(
    count: number,
    render: (index: number) => ReactNode,
): ReactNode[] {
    return Array.from({ length: count }, (_, index) => render(index));
}
