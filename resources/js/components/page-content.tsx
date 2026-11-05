import { usePage } from '@inertiajs/react';
import { RotateCwIcon } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { usePageLoadStatus } from '@/hooks/use-page-load-status';
import { loadPageProps } from '@/lib/page-load';
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
    const { stillLoading, failure } = usePageLoadStatus();

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
                    onClick={() => loadPageProps(page)}
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
                <Button
                    variant="link"
                    size="sm"
                    onClick={() => loadPageProps(page)}
                >
                    Retry
                </Button>
            ) : null}
        </div>
    );
}
