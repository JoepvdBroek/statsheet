import { useSyncExternalStore } from 'react';
import {
    currentPageLoad,
    normalPageLoad,
    subscribeToPageLoad,
} from '@/lib/page-load';
import type { PageLoad } from '@/lib/page-load';

/** How the current page's own load is going: still loading after 8 s, or failed (see lib/page-load). */
export function usePageLoad(): PageLoad {
    return useSyncExternalStore(
        subscribeToPageLoad,
        currentPageLoad,
        normalPageLoad,
    );
}
