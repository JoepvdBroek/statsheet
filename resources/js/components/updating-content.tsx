import type { ReactNode } from 'react';
import { QuietSpinner } from '@/components/quiet-spinner';
import { cn } from '@/lib/utils';

/**
 * Content a same-page reload refreshes, such as a list under its search or a chart under its select. While
 * `updating`, from 150 ms into the reload (see useSamePageReload), it dims, can't be pressed and is aria-busy.
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
