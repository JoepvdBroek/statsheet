import { router } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import { whenPageVisitsSettle } from '@/lib/page-visits';

/** How long a prefetched page may be used before it's fetched again. */
const prefetchCacheFor = '30s';

/** Methods that change data. QUERY is a read, like GET. */
const mutationMethods = ['post', 'put', 'patch', 'delete'];

/**
 * Prefetches the given pages once the current page's own props have arrived, so the prefetches never compete with
 * the page's request: an instant visit shows its page at tap and fetches its props after. The wait starts with one
 * macrotask, by which time the request of an instant visit has started, and then lasts until no page visit is in
 * flight (see {@link whenPageVisitsSettle}). That also puts a mutation's flush (see
 * {@link flushPrefetchesAfterMutations}) before these prefetches, so it won't throw them away. Returns a function
 * that cancels prefetches that haven't started yet.
 */
export function prefetchAfterVisit(
    hrefs: Array<string | UrlMethodPair>,
): () => void {
    let cancelWait = () => {};

    const timer = window.setTimeout(() => {
        cancelWait = whenPageVisitsSettle(() => {
            for (const href of hrefs) {
                router.prefetch(href, {}, { cacheFor: prefetchCacheFor });
            }
        });
    }, 0);

    return () => {
        window.clearTimeout(timer);
        cancelWait();
    };
}

/**
 * Flushes the whole prefetch cache, in-flight prefetches included, after every successful mutation visit, so a
 * prefetched page never misses a change the owner just made. This covers the background Workout logging visits too,
 * since they are Inertia visits as well. Returns a function that stops listening.
 */
export function flushPrefetchesAfterMutations(): () => void {
    const mutationVisitIds = new Set<string>();

    const stopListeningToStart = router.on('start', (event) => {
        const { visit } = event.detail;

        if (!visit.prefetch && mutationMethods.includes(visit.method)) {
            mutationVisitIds.add(visit.id);
        }
    });

    const stopListeningToSuccess = router.on('success', (event) => {
        const { visitId } = event.detail;

        if (visitId !== undefined && mutationVisitIds.has(visitId)) {
            router.flushAll();
        }
    });

    const stopListeningToFinish = router.on('finish', (event) => {
        mutationVisitIds.delete(event.detail.visit.id);
    });

    return () => {
        stopListeningToStart();
        stopListeningToSuccess();
        stopListeningToFinish();
    };
}
