import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { focusPageHeading } from '@/lib/page-heading-focus';
import { refreshRestoredPage } from '@/lib/page-refresh';

/** Why the current page couldn't load its props: no answer, or the server's answer with its status. */
export type PageFailure =
    | { kind: 'network' }
    | { kind: 'status'; status: number };

/**
 * How the current page's own load is going, for PageContent: still loading after 8 s, or failed. Both stay until
 * the next page change, or until the page's props arrive.
 */
export type PageLoadStatus = {
    stillLoading: boolean;
    failure: PageFailure | null;
};

/** The status before anything went wrong, and on the server. */
export const loadingNormally: PageLoadStatus = {
    stillLoading: false,
    failure: null,
};

let status = loadingNormally;

const listeners = new Set<() => void>();

function setStatus(next: PageLoadStatus): void {
    status = next;
    listeners.forEach((listener) => listener());
}

/** Subscribes to changes of the current page's load status, for useSyncExternalStore. Returns a function that unsubscribes. */
export function subscribeToPageLoadStatus(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** The current page's load status, for useSyncExternalStore. */
export function currentPageLoadStatus(): PageLoadStatus {
    return status;
}

/** Shows the "Still loading — slow connection" hint under the current page's skeleton. */
export function showStillLoading(): void {
    if (status.failure === null) {
        setStatus({ stillLoading: true, failure: null });
    }
}

/**
 * Shows the in-page error state in place of the current page's content: its header and the tab bar stay. Also for a
 * page whose silent refresh finds it gone or not yours (404/403), from that reload's own onHttpException.
 */
export function showPageFailure(failure: PageFailure): void {
    setStatus({ stillLoading: false, failure });
}

/** Ends the hint or error state, once the page's props have arrived or another page shows. */
export function clearPageLoad(): void {
    if (status !== loadingNormally) {
        setStatus(loadingNormally);
    }
}

/**
 * Loads the given page's props again, as an instant visit to it that replaces its history entry: its skeleton shows
 * while the request runs, and a failure shows the error state again. For Retry, and for a page restored without
 * its props. A replacing visit fires no `navigate`, so this ends the hint or error state itself, and moves focus to
 * the page's h1, as Retry is gone with them.
 */
export function loadPageProps({
    component,
    url,
}: Pick<Page, 'component' | 'url'>): void {
    clearPageLoad();
    focusPageHeading();
    router.visit(url, { component, replace: true, preserveScroll: true });
}

/**
 * Whether the page has none of its own props, only the shared ones: the placeholder an instant visit drew at tap,
 * left in history when its request failed or was left behind.
 */
export function isWithoutItsProps({ props, sharedProps = [] }: Page): boolean {
    return Object.keys(props).every(
        (key) => key === 'errors' || sharedProps.includes(key),
    );
}

/**
 * Ends the hint or error state on every page change. A page restored by back/forward loads its props when it has
 * none, so a skeleton or error page left in history never sticks, and otherwise refreshes them silently (see
 * {@link refreshRestoredPage}), as they're as old as the page's history entry. Returns a function that stops listening.
 */
export function trackPageLoad(): () => void {
    return router.on('navigate', (event) => {
        const { page, type } = event.detail;

        clearPageLoad();

        if (type !== 'history') {
            return;
        }

        if (isWithoutItsProps(page)) {
            loadPageProps(page);
        } else {
            refreshRestoredPage(page);
        }
    });
}
