import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { focusPageHeading } from '@/lib/page-heading-focus';

/** Why the current page couldn't load its props: no answer, or the server's answer with its status. */
export type PageFailure =
    | { kind: 'network' }
    | { kind: 'status'; status: number };

/**
 * How the current page's own load is going, for PageContent: still loading after 8 s, or failed. Both stay until
 * the next page change, or until the page's props arrive.
 */
export type PageLoad = {
    stillLoading: boolean;
    failure: PageFailure | null;
};

const loadingNormally: PageLoad = { stillLoading: false, failure: null };

let pageLoad = loadingNormally;

const listeners = new Set<() => void>();

function setPageLoad(next: PageLoad): void {
    pageLoad = next;
    listeners.forEach((listener) => listener());
}

/** Subscribes to changes of the current page's load, for useSyncExternalStore. Returns a function that unsubscribes. */
export function subscribeToPageLoad(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** The current page's load, for useSyncExternalStore. */
export function currentPageLoad(): PageLoad {
    return pageLoad;
}

/** The page's load before anything went wrong, and on the server. */
export function normalPageLoad(): PageLoad {
    return loadingNormally;
}

/** Shows the "Still loading — slow connection" hint under the current page's skeleton. */
export function showStillLoading(): void {
    if (pageLoad.failure === null) {
        setPageLoad({ stillLoading: true, failure: null });
    }
}

/**
 * Shows the in-page error state in place of the current page's content: its header and the tab bar stay. Also for a
 * page whose silent refresh finds it gone or not yours (404/403), from that reload's own onHttpException.
 */
export function showPageFailure(failure: PageFailure): void {
    setPageLoad({ stillLoading: false, failure });
}

/** Ends the hint or error state, once the page's props have arrived or another page shows. */
export function clearPageLoad(): void {
    if (pageLoad !== loadingNormally) {
        setPageLoad(loadingNormally);
    }
}

/**
 * Loads the given page's props again, as an instant visit to it that replaces its history entry: its skeleton shows
 * while the request runs, and a failure shows the error state again. For Retry, and for a page restored without
 * its props. A replacing visit fires no `navigate`, so this ends the hint or error state itself, and moves focus to
 * the page's h1, as Retry is gone with them.
 */
export function loadPage({
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
 * Ends the hint or error state on every page change, and loads the props of a page restored by back/forward
 * without them, so a skeleton or error page left in history never sticks. Returns a function that stops listening.
 */
export function trackPageLoad(): () => void {
    return router.on('navigate', (event) => {
        const { page, type } = event.detail;

        clearPageLoad();

        if (type === 'history' && isWithoutItsProps(page)) {
            loadPage(page);
        }
    });
}
