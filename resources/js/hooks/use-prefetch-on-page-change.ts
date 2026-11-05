import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { prefetchAfterVisit } from '@/lib/prefetch';
import { toUrl } from '@/lib/utils';

/**
 * Prefetches the given pages after every page change, so a tap on them swaps without waiting for the server. Hover
 * prefetching never fires on a phone, so this is how the likely next taps get ahead. The current page is never
 * prefetched, and a page still fresh in the cache isn't fetched again.
 */
export function usePrefetchOnPageChange(
    hrefs: Array<NonNullable<InertiaLinkProps['href']>>,
): void {
    const { url } = usePage();
    const urls = hrefs.map(toUrl).join('\n');

    useEffect(() => {
        if (urls === '') {
            return;
        }

        return prefetchAfterVisit(urls.split('\n'));
    }, [url, urls]);
}
