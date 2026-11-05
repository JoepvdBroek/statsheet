import type { PendingVisit, VisitOptions } from '@inertiajs/core';
import { useCallback, useEffect, useRef, useState } from 'react';

/** How long a same-page reload runs before it shows, so a quick one shows nothing. */
const updatingDelay = 150;

/** Visit options that track a reload started from the given field. */
export type PageReloadOptions<Field extends string> = (
    field: Field,
) => Pick<VisitOptions, 'onStart' | 'onFinish'>;

/**
 * Feedback for same-page reloads, such as a search or a filter: 150 ms into a reload, the field it was started from
 * is `updatingField` until it lands, for the content to dim and that field to show a spinner. There is no page-level
 * signal and no skeleton. A newer reload takes over from one still running. `reloadOptions` stays the same function
 * across renders, so effects can depend on it.
 */
export function usePageReload<Field extends string>(): {
    /** The field whose reload is running and has taken over 150 ms; null otherwise. */
    updatingField: Field | null;
    reloadOptions: PageReloadOptions<Field>;
} {
    const [updatingField, setUpdatingField] = useState<Field | null>(null);
    const running = useRef<{ visitId: string; timer: number } | null>(null);

    useEffect(() => () => window.clearTimeout(running.current?.timer), []);

    const reloadOptions = useCallback(
        (field: Field) => ({
            onStart: (visit: PendingVisit) => {
                window.clearTimeout(running.current?.timer);
                running.current = {
                    visitId: visit.id,
                    timer: window.setTimeout(
                        () => setUpdatingField(field),
                        updatingDelay,
                    ),
                };
            },
            onFinish: (visit: PendingVisit) => {
                if (running.current?.visitId !== visit.id) {
                    return;
                }

                window.clearTimeout(running.current.timer);
                running.current = null;
                setUpdatingField(null);
            },
        }),
        [],
    );

    return { updatingField, reloadOptions };
}
