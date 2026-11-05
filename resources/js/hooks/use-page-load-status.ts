import { useSyncExternalStore } from 'react';
import {
    currentPageLoadStatus,
    loadingNormally,
    subscribeToPageLoadStatus,
} from '@/lib/page-load';
import type { PageLoadStatus } from '@/lib/page-load';

/** How the current page's own load is going: still loading after 8 s, or failed (see lib/page-load). */
export function usePageLoadStatus(): PageLoadStatus {
    return useSyncExternalStore(
        subscribeToPageLoadStatus,
        currentPageLoadStatus,
        () => loadingNormally,
    );
}
