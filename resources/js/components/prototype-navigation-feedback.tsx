/**
 * PROTOTYPE (throwaway) for the wayfinder ticket "How should a pending navigation look?".
 * Four looks for the time between a tap and the new page, switchable from the floating bar,
 * with a simulated network latency (cookie read by PrototypeLatency) and an adjustable wait-signal delay.
 * Lives on the `prototype/navigation-feedback` branch only; never merge it.
 */
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useSyncExternalStore } from 'react';
import { createPortal } from 'react-dom';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import type { AppTab } from '@/types';

export const VARIANTS = {
    A: 'Tapped element',
    B: 'Overlay',
    C: 'Shell + skeleton',
    D: 'Bold bar',
} as const;
type Variant = keyof typeof VARIANTS;
type Kind = 'page' | 'action' | 'reload';

type State = {
    variant: Variant;
    delay: number;
    latency: number;
    bar: boolean;
    pending: null | {
        id: string;
        kind: Kind;
        targetPath: string;
        trigger: HTMLElement | null;
        startedAt: number;
    };
    showWait: boolean;
    last: string;
};

const stored = (key: string, fallback: string) =>
    typeof localStorage === 'undefined'
        ? fallback
        : (localStorage.getItem(`prototype-nav:${key}`) ?? fallback);

let state: State = {
    variant: stored('variant', 'A') as Variant,
    delay: Number(stored('delay', '150')),
    latency: Number(stored('latency', '800')),
    bar: stored('bar', 'on') === 'on',
    pending: null,
    showWait: false,
    last: 'no navigation yet',
};
const listeners = new Set<() => void>();
const set = (patch: Partial<State>) => {
    state = { ...state, ...patch };
    listeners.forEach((listener) => listener());
};
const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => listeners.delete(listener);
};

export function usePrototypeNavigation(): State {
    return useSyncExternalStore(subscribe, () => state);
}

function saveSetting(
    key: 'variant' | 'delay' | 'latency' | 'bar',
    value: string,
) {
    localStorage.setItem(`prototype-nav:${key}`, value);

    if (key === 'latency') {
        document.cookie = `prototype_latency=${value}; path=/; SameSite=Lax`;
    }

    set({
        [key]:
            key === 'variant'
                ? value
                : key === 'bar'
                  ? value === 'on'
                  : Number(value),
    } as Partial<State>);
}

/** The tab a path belongs to, so variant C can move the active tab at tap time. */
export function tabForPath(path: string): AppTab | null {
    if (path === '/' || path.startsWith('/routines')) {
        return 'routines';
    }

    if (path.startsWith('/workouts')) {
        return 'workout';
    }

    if (/^\/(statistics|exercises|goals|reviews)/.test(path)) {
        return 'stats';
    }

    return null;
}

let lastTap: { element: HTMLElement; at: number } | null = null;
let waitTimer: number | undefined;
let installed = false;

function install() {
    if (installed) {
        return;
    }

    installed = true;
    document.cookie = `prototype_latency=${state.latency}; path=/; SameSite=Lax`;

    document.addEventListener(
        'pointerdown',
        (event) => {
            const element = (event.target as HTMLElement).closest<HTMLElement>(
                'a,button,[role=menuitem],select,input,textarea',
            );
            lastTap = element ? { element, at: performance.now() } : null;
        },
        true,
    );

    router.on('start', (event) => {
        const visit = event.detail.visit as typeof event.detail.visit & {
            prefetch?: boolean;
            deferredProps?: boolean;
            poll?: boolean;
        };

        if (
            visit.prefetch ||
            visit.async ||
            visit.deferredProps ||
            visit.poll ||
            visit.only.length
        ) {
            return;
        }

        const recentTap =
            lastTap &&
            lastTap.element.isConnected &&
            performance.now() - lastTap.at < 1500
                ? lastTap.element
                : null;
        const focused =
            document.activeElement instanceof HTMLElement &&
            document.activeElement !== document.body
                ? document.activeElement
                : null;
        const trigger = recentTap ?? focused;
        const samePath = visit.url.pathname === window.location.pathname;

        let kind: Kind | null;

        if (visit.method === 'get') {
            kind = samePath ? 'reload' : 'page';
        } else {
            kind = trigger?.closest('[data-prototype-navigates]')
                ? 'action'
                : null;
        }

        if (!kind) {
            return;
        }

        clear();
        trigger?.setAttribute('data-nav-pending', kind);
        set({
            pending: {
                id: visit.id,
                kind,
                targetPath: visit.url.pathname,
                trigger,
                startedAt: performance.now(),
            },
            showWait: state.delay === 0,
        });

        if (state.delay > 0) {
            waitTimer = window.setTimeout(
                () => set({ showWait: true }),
                state.delay,
            );
        }
    });

    router.on('finish', (event) => {
        const pending = state.pending;

        if (!pending || pending.id !== event.detail.visit.id) {
            return;
        }

        const took = Math.round(performance.now() - pending.startedAt);
        clear();
        set({
            last: `${pending.kind} → ${pending.targetPath} · ${took} ms · wait signal ${took >= state.delay ? 'shown' : 'not shown'}`,
        });
    });
}

function clear() {
    window.clearTimeout(waitTimer);
    state.pending?.trigger?.removeAttribute('data-nav-pending');
    set({ pending: null, showWait: false });
}

/** Per-variant tap acknowledgement on the tapped element, plus hiding Inertia's own bar when switched off. */
const css = `
html:not([data-proto-bar="on"]) #nprogress { display: none !important; }
[data-nav-pending] { transition: opacity 120ms, transform 120ms, background-color 120ms; }
html[data-proto-variant="A"] [data-nav-pending] { background-color: color-mix(in oklab, var(--primary) 18%, transparent); border-radius: 0.5rem; }
html[data-proto-variant="B"] [data-nav-pending] { opacity: 0.55; transform: scale(0.97); }
html[data-proto-variant="C"] [data-nav-pending] { opacity: 0.6; }
html[data-proto-variant="D"] [data-nav-pending] { color: var(--primary) !important; text-decoration: underline; text-underline-offset: 4px; }
@keyframes proto-indeterminate { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
`;

/** The variant layers that sit above the page (A spinner, B overlay, D bar), the shared action/reload spinner, and the switcher. */
export function PrototypeNavigationFeedback() {
    const nav = usePrototypeNavigation();

    useEffect(() => {
        if (import.meta.env.DEV) {
            install();
        }
    }, []);

    useEffect(() => {
        document.documentElement.dataset.protoVariant = nav.variant;
        document.documentElement.dataset.protoBar = nav.bar ? 'on' : 'off';
    }, [nav.variant, nav.bar]);

    if (!import.meta.env.DEV) {
        return null;
    }

    const waiting = nav.pending && nav.showWait ? nav.pending : null;
    const triggerSpinner =
        waiting &&
        waiting.trigger &&
        (nav.variant === 'A' || waiting.kind !== 'page');

    return (
        <>
            <style>{css}</style>
            {triggerSpinner ? <SpinnerAt element={waiting.trigger!} /> : null}
            {waiting && waiting.kind !== 'reload' && nav.variant === 'B' ? (
                <div className="fixed inset-x-0 top-0 bottom-(--tab-bar-height) z-30 flex flex-col items-center justify-center gap-3 bg-background/70 backdrop-blur-[2px]">
                    <Spinner className="size-8 text-primary" />
                    <span className="text-sm text-muted-foreground">
                        Loading…
                    </span>
                </div>
            ) : null}
            {waiting && waiting.kind !== 'reload' && nav.variant === 'D' ? (
                <div className="fixed inset-x-0 top-0 z-50 h-1 overflow-hidden bg-primary/20">
                    <div className="h-full w-2/5 animate-[proto-indeterminate_900ms_ease-in-out_infinite] bg-primary shadow-[0_0_8px_var(--primary)]" />
                </div>
            ) : null}
            <Switcher nav={nav} />
        </>
    );
}

function SpinnerAt({ element }: { element: HTMLElement }) {
    const rect = element.getBoundingClientRect();
    const size = 16;

    return createPortal(
        <span
            className="pointer-events-none fixed z-50 flex items-center justify-center rounded-full bg-background/90 p-0.5"
            style={{
                top: rect.top + rect.height / 2 - size / 2 - 2,
                left: rect.right - size - 10,
            }}
        >
            <Spinner className="size-4 text-primary" />
        </span>,
        document.body,
    );
}

/** Variant C: the skeleton shown in place of the page while a page change is waiting. */
export function PrototypePageSkeleton() {
    return (
        <div
            aria-busy="true"
            className="flex flex-col gap-4 px-4 pt-[calc(env(safe-area-inset-top)+1.5rem)]"
        >
            <Skeleton className="h-9 w-1/2" />
            <Skeleton className="h-28 w-full rounded-xl" />
            <Skeleton className="h-28 w-full rounded-xl" />
            <Skeleton className="h-16 w-full rounded-xl" />
            <Skeleton className="h-16 w-full rounded-xl" />
        </div>
    );
}

function Switcher({ nav }: { nav: State }) {
    const keys = Object.keys(VARIANTS) as Variant[];
    const cycle = (step: number) =>
        saveSetting(
            'variant',
            keys[
                (keys.indexOf(nav.variant) + step + keys.length) % keys.length
            ],
        );

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement;

            if (target.closest('input,textarea,select,[contenteditable]')) {
                return;
            }

            if (event.key === 'ArrowLeft') {
                cycle(-1);
            }

            if (event.key === 'ArrowRight') {
                cycle(1);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    const select = 'rounded bg-zinc-800 px-1 py-0.5 text-[11px] text-white';

    return (
        <div className="fixed bottom-[calc(var(--tab-bar-height)+8px)] left-1/2 z-[70] flex w-[min(96vw,30rem)] -translate-x-1/2 flex-col gap-1 rounded-2xl border border-dashed border-amber-400 bg-zinc-950/95 px-3 py-2 text-[11px] text-white shadow-xl">
            <div className="flex items-center justify-between gap-2">
                <button
                    type="button"
                    aria-label="Previous variant"
                    onClick={() => cycle(-1)}
                    className="rounded p-1 hover:bg-white/10"
                >
                    <ChevronLeft className="size-4" />
                </button>
                <span className="font-medium">
                    PROTOTYPE {nav.variant} · {VARIANTS[nav.variant]}
                </span>
                <button
                    type="button"
                    aria-label="Next variant"
                    onClick={() => cycle(1)}
                    className="rounded p-1 hover:bg-white/10"
                >
                    <ChevronRight className="size-4" />
                </button>
            </div>
            <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                <label>
                    latency{' '}
                    <select
                        className={select}
                        value={nav.latency}
                        onChange={(event) =>
                            saveSetting('latency', event.target.value)
                        }
                    >
                        {[0, 300, 800, 2000].map((ms) => (
                            <option key={ms} value={ms}>
                                {ms} ms
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    signal after{' '}
                    <select
                        className={select}
                        value={nav.delay}
                        onChange={(event) =>
                            saveSetting('delay', event.target.value)
                        }
                    >
                        {[0, 150, 300, 500].map((ms) => (
                            <option key={ms} value={ms}>
                                {ms} ms
                            </option>
                        ))}
                    </select>
                </label>
                <label className="flex items-center gap-1">
                    <input
                        type="checkbox"
                        checked={nav.bar}
                        onChange={(event) =>
                            saveSetting(
                                'bar',
                                event.target.checked ? 'on' : 'off',
                            )
                        }
                    />
                    Inertia bar
                </label>
            </div>
            <div className="truncate text-center text-white/60">
                {nav.pending
                    ? `pending ${nav.pending.kind} → ${nav.pending.targetPath}${nav.showWait ? ' · wait signal on' : ''}`
                    : `last: ${nav.last}`}
            </div>
        </div>
    );
}
