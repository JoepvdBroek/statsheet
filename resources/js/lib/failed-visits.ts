import type { Page, PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    clearPageLoad,
    showPageFailure,
    showStillLoading,
} from '@/lib/page-load';
import type { PageFailure } from '@/lib/page-load';
import { isPageVisit } from '@/lib/page-visits';

/** How long a page change runs before the "Still loading" hint shows. */
const stillLoadingAfter = 8_000;

/** How long any visit runs before it's cancelled and treated as failed. Inertia itself never times out. */
const giveUpAfter = 20_000;

/**
 * What a visit does, which decides what its failure shows:
 * - `page`: a page change, an instant visit to a page: the in-page error state.
 * - `action`: an instant Start, Finish or Delete Workout: back to the page acted from, reloaded, and a toast.
 * - `submit`: any other change, such as saving a Routine or logging in: a toast.
 * - `reload`: a same-page reload, such as a search: a toast.
 * - `leave`: a plain visit to another page, which stays on the current one until it lands: a toast.
 */
type VisitKind = 'page' | 'action' | 'submit' | 'reload' | 'leave';

/**
 * What the visits named with {@link nameVisit} do, as in "Couldn't finish the workout — try again". A change that
 * isn't named is a save.
 */
const visitDescriptions = {
    startWorkout: 'start the workout',
    finishWorkout: 'finish the workout',
    deleteWorkout: 'delete the workout',
    logIn: 'log in',
    logOut: 'log out',
    confirmPassword: 'confirm your password',
    resetPassword: 'reset your password',
    sendResetLink: 'send the reset link',
    sendVerificationEmail: 'send the verification email',
} as const;

export type NamedVisit = keyof typeof visitDescriptions;

/** The visit in flight that a failure belongs to: a sync visit, as only one runs at a time. */
type WatchedVisit = {
    id: string;
    kind: VisitKind;
    url: URL;
    /** What the visit does, as in "Couldn't finish the workout". */
    description: string;
    /** For an action: the page it was started from, once read from history. */
    origin: Page | null;
    timers: number[];
};

/** What each named visit does, by visit id, from {@link nameVisit} until its `before` event. */
const visitNames = new Map<string, NamedVisit>();

/** Visits whose failures are left alone, by id, from {@link skipFailureHandling} until their `before` event. */
const skippedVisitIds = new Set<string>();

let watched: WatchedVisit | null = null;

/**
 * Names what a visit does, for its failure toast: `finishWorkout` gives "Couldn't finish the workout — try again".
 * Call it from the visit's onBefore. Instant actions are always named; other changes are only named when they
 * aren't saves, such as logging in.
 */
export function nameVisit(visit: PendingVisit, name: NamedVisit): void {
    visitNames.set(visit.id, name);
}

/**
 * Leaves a visit's failure alone: no 20 s stall, no toast, as for a background visit. For in-place actions during a
 * Workout, such as adding or swapping an Exercise, which get no feedback. Call it from the visit's onBefore.
 */
export function skipFailureHandling(visit: PendingVisit): void {
    skippedVisitIds.add(visit.id);
}

/**
 * Handles visits that fail or stall: no answer from the network, a server error (status 400 or more), or no answer
 * in 20 s, when the visit is cancelled. A page change shows the in-page error state, after an 8 s "Still loading"
 * hint; an instant action goes back to the page it was started from and reloads it; a submit or a same-page reload
 * just unlocks (Inertia's own `processing`) and toasts. Nothing is retried on its own.
 *
 * In production the server answers a failed Inertia visit with an Inertia response (AppServiceProvider), which is
 * handled here instead of Inertia drawing it. In development it answers with Laravel's HTML debug page, which
 * keeps Inertia's modal, over the same handling.
 *
 * Background visits (see isPageVisit) and visits passed to {@link skipFailureHandling} aren't watched: in production
 * their server errors only toast. Inertia's failure events don't say which visit failed, so a server error while a
 * watched visit runs is taken as that visit's. A visit that handles its own failure, by returning false from
 * onHttpException or onNetworkError, skips this. Returns a function that stops handling.
 */
export function handleFailedVisits(): () => void {
    const stopListening = [
        router.on('before', (event) => {
            const { visit } = event.detail;

            if (!isPageVisit(visit)) {
                return;
            }

            stopWatching();

            if (!skippedVisitIds.delete(visit.id)) {
                watched = watch(visit);
            }
        }),
        router.on('finish', (event) => {
            settle(event.detail.visit.id);
        }),
        router.on('success', (event) => {
            settle(event.detail.visitId);
        }),
        router.on('networkError', (event) => {
            const { error } = event.detail;

            if (watched !== null && isSameRequest(error, watched.url)) {
                fail({ kind: 'network' });
            }
        }),
        router.on('httpException', (event) => {
            const { response } = event.detail;
            const isInertiaResponse =
                response.headers['x-inertia'] !== undefined;

            if (watched !== null) {
                fail({ kind: 'status', status: response.status });
            } else if (isInertiaResponse) {
                toast.error('Something went wrong — try again', {
                    id: 'failed-visit',
                });
            }

            if (isInertiaResponse) {
                return false;
            }
        }),
    ];

    return () => {
        stopWatching();
        stopListening.forEach((stop) => stop());
    };
}

function watch(visit: PendingVisit): WatchedVisit {
    const kind = kindOf(visit);
    const timers = [window.setTimeout(giveUp, giveUpAfter)];

    if (kind === 'page') {
        timers.push(window.setTimeout(showStillLoading, stillLoadingAfter));
    }

    const name = visitNames.get(visit.id);
    visitNames.delete(visit.id);

    const watchedVisit: WatchedVisit = {
        id: visit.id,
        kind,
        url: visit.url,
        description: name ? visitDescriptions[name] : 'save',
        origin: null,
        timers,
    };

    if (kind === 'action') {
        // Before the instant swap, the current history entry is still the page the action was started from.
        void router.decryptHistory().then((page) => {
            watchedVisit.origin = page;
        });
    }

    return watchedVisit;
}

function kindOf({ method, component, url }: PendingVisit): VisitKind {
    if (method.toLowerCase() !== 'get') {
        return component ? 'action' : 'submit';
    }

    if (component) {
        return 'page';
    }

    return url.pathname === window.location.pathname ? 'reload' : 'leave';
}

/** The watched visit's response has landed or it was cancelled: stops watching it and ends the hint. */
function settle(visitId: string | undefined): void {
    if (watched === null || watched.id !== visitId) {
        return;
    }

    if (watched.kind === 'page') {
        clearPageLoad();
    }

    stopWatching();
}

function stopWatching(): void {
    watched?.timers.forEach((timer) => window.clearTimeout(timer));
    watched = null;
}

/** No answer in 20 s: cancels the visit and fails it as if the network had. */
function giveUp(): void {
    fail({ kind: 'network' });
    router.cancelAll({ async: false });
}

function fail(failure: PageFailure): void {
    const failed = watched;

    stopWatching();

    switch (failed?.kind) {
        case 'page':
            showPageFailure(failure);
            break;
        case 'action':
            goBackAndReload(failed.origin);
            toast.error(`Couldn't ${failed.description} — try again`);
            break;
        case 'submit':
            toast.error(`Couldn't ${failed.description} — try again`);
            break;
        case 'reload':
            toast.error("Couldn't update — try again");
            break;
        case 'leave':
            toast.error("Couldn't load this page — try again");
            break;
    }
}

/**
 * Back to the page an instant action was started from, which then reloads to show the true state, since the action
 * may or may not have reached the server: every page restored by back/forward does (see trackPageLoad).
 *
 * History keeps the action's placeholder page ahead of the origin, at the action's address. So it first becomes a
 * copy of the origin: Forward then shows the origin again, rather than load the action's address as a page (a GET to
 * Finish Workout, say).
 */
function goBackAndReload(origin: Page | null): void {
    window.setTimeout(() => {
        if (origin === null) {
            window.history.back();

            return;
        }

        router.replace({
            component: origin.component,
            url: origin.url,
            props: origin.props,
            onFinish: () => window.history.back(),
        });
    }, 0);
}

/** Whether a network error is the watched request's, rather than a background one's that failed alongside it. */
function isSameRequest(error: Error, url: URL): boolean {
    const failedUrl = 'url' in error ? error.url : undefined;

    if (typeof failedUrl !== 'string') {
        return true;
    }

    const failed = new URL(failedUrl, window.location.href);

    return failed.pathname === url.pathname && failed.search === url.search;
}
