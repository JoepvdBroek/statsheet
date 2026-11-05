import { router } from '@inertiajs/react';

/** Ids of the page visits whose response hasn't landed yet: sync visits, not prefetches or async reloads. */
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

        if (!visit.prefetch && !visit.async) {
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
