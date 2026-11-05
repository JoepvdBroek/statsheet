import { router } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';

/** How long a prefetched page may be used before it's fetched again. */
const prefetchCacheFor = '30s';

/** Methods that change data. QUERY is a read, like GET. */
const mutationMethods = ['post', 'put', 'patch', 'delete'];

/**
 * Prefetches the given pages once the current visit has settled. The wait is one macrotask: a page change swaps the
 * page synchronously and fires the visit's `success` in the microtasks after it, so a mutation's flush (see
 * {@link flushPrefetchesAfterMutations}) has already run and won't throw these fresh prefetches away. Returns a
 * function that cancels prefetches that haven't started yet.
 */
export function prefetchAfterVisit(
    hrefs: Array<string | UrlMethodPair>,
): () => void {
    const timer = window.setTimeout(() => {
        for (const href of hrefs) {
            router.prefetch(href, {}, { cacheFor: prefetchCacheFor });
        }
    }, 0);

    return () => window.clearTimeout(timer);
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
