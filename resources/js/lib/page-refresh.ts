import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { showPageFailure } from '@/lib/page-load';
import { isBackgroundLoad } from '@/lib/page-visits';

/** Statuses that mean the page itself is gone or not the owner's, rather than that the refresh failed. */
const pageGoneStatuses = [403, 404];

/** Cancels the refresh in flight, if any. */
let cancelRefresh = (): void => {};

/**
 * Refreshes a page restored by back/forward, which shows its props as they were when it was left: reloads the props
 * it shows once, in the background, and swaps the fresh ones in when they land. The swap is silent: no skeleton,
 * dimming, toast or announcement, and scroll, focus and open inputs stay, as it's an async reload that replaces the
 * history entry and fires no `navigate`. A page that's gone or not the owner's (404/403) shows the in-page error
 * state; any other failure or a stall keeps the restored props quietly.
 *
 * One request covers the props the restored page has, deferred ones included; deferred props it's missing load
 * through Inertia's own deferred requests. An infinite-scroll list is refreshed only while it has its first page
 * alone, which the refresh replaces; with more pages loaded it keeps them, as one request can't bring them all back.
 *
 * A newer visit (a navigation, a reload, or an action such as logging a Set) cancels the refresh, as its response
 * carries fresh props too, and a refresh landing after it would bring back older ones. Back/forward cancels it too.
 */
export function refreshRestoredPage(page: Page): void {
    cancelRefresh();

    const { only, reset } = propsToRefresh(page);
    let cancelToken: { cancel: () => void } | null = null;
    let stopWatchingNewerVisits = (): void => {};

    const cancelThisRefresh = () => cancelToken?.cancel();
    cancelRefresh = cancelThisRefresh;

    router.reload({
        only,
        reset,
        preserveErrors: true,
        onCancelToken: (token) => {
            cancelToken = token;
        },
        onStart: () => {
            stopWatchingNewerVisits = router.on('start', (event) => {
                if (!isBackgroundLoad(event.detail.visit)) {
                    cancelToken?.cancel();
                }
            });
        },
        onFinish: () => {
            stopWatchingNewerVisits();

            if (cancelRefresh === cancelThisRefresh) {
                cancelRefresh = () => {};
            }
        },
        onHttpException: (response) => {
            if (pageGoneStatuses.includes(response.status)) {
                showPageFailure({ kind: 'status', status: response.status });
            }

            return false;
        },
        onNetworkError: () => false,
    });
}

/**
 * The page's own and shared props to reload, without `errors`, which always come along and are kept. A merging prop,
 * such as an infinite-scroll list, is left out, unless it's a list with only its first page loaded: that one is reset
 * (Inertia requests reset props along with `only`), so the fresh first page replaces the restored one rather than
 * being appended to it. Lists are told by their scroll metadata as well, since a partial reload's response drops the
 * page's merge metadata.
 */
function propsToRefresh({
    props,
    mergeProps = [],
    prependProps = [],
    deepMergeProps = [],
    scrollProps = {},
}: Page): { only: string[]; reset: string[] } {
    const mergingProps = new Set([
        ...[...mergeProps, ...prependProps, ...deepMergeProps].map(
            (path) => path.split('.')[0],
        ),
        ...Object.keys(scrollProps),
    ]);
    const only: string[] = [];
    const reset: string[] = [];

    for (const key of Object.keys(props)) {
        if (key === 'errors') {
            continue;
        }

        if (!mergingProps.has(key)) {
            only.push(key);
        } else if (scrollProps[key]?.previousPage === null) {
            reset.push(key);
        }
    }

    return { only, reset };
}
