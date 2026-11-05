import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';

/**
 * Whether a visit is a page visit: a sync visit, which the owner started and waits for, such as a page change, a
 * save or a search. Prefetches and async visits (deferred props, polls, background reloads, Workout logging) run in
 * the background and aren't.
 */
export function isPageVisit(visit: PendingVisit): boolean {
    return !visit.prefetch && !visit.async;
}

/**
 * Whether a visit loads in the background without bringing the page's props: a prefetch, or a load of the page's
 * deferred props (an internal flag of Inertia's visit, hence the check). Other async visits, such as Workout logging,
 * do bring the page's props, even though they aren't page visits.
 */
export function isBackgroundLoad(visit: PendingVisit): boolean {
    return (
        visit.prefetch ||
        ('deferredProps' in visit && visit.deferredProps === true)
    );
}

/** Ids of the page visits whose response hasn't landed yet. */
const visitsInFlight = new Set<string>();

/** Callbacks waiting for every page visit in flight to finish. */
const settleCallbacks = new Set<() => void>();

/**
 * Keeps track of the page visits in flight, for {@link whenPageVisitsSettle}. An instant visit shows its page at tap
 * and only then sends its request, so the page being on screen doesn't mean its props have arrived. Returns a
 * function that stops tracking.
 */
export function trackPageVisits(): () => void {
    const stopListeningToStart = router.on('start', (event) => {
        const { visit } = event.detail;

        if (isPageVisit(visit)) {
            visitsInFlight.add(visit.id);
        }
    });

    const stopListeningToFinish = router.on('finish', (event) => {
        if (
            !visitsInFlight.delete(event.detail.visit.id) ||
            visitsInFlight.size > 0
        ) {
            return;
        }

        const callbacks = [...settleCallbacks];
        settleCallbacks.clear();
        callbacks.forEach((callback) => callback());
    });

    return () => {
        stopListeningToStart();
        stopListeningToFinish();
    };
}

/**
 * Calls back once no page visit is in flight any more, straight away when none is. Returns a function that cancels
 * a callback still waiting.
 */
export function whenPageVisitsSettle(callback: () => void): () => void {
    if (visitsInFlight.size === 0) {
        callback();

        return () => {};
    }

    settleCallbacks.add(callback);

    return () => settleCallbacks.delete(callback);
}
