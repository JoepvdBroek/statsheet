/**
 * Every page's lazy chunk, matched the same way the Inertia Vite plugin resolves pages, so these imports load the very
 * chunks a visit would.
 */
const pages = import.meta.glob('../pages/**/*.tsx');

/**
 * Loads every page's code in the background once the app has loaded and the browser is idle, so even the first visit
 * to a page swaps without waiting for its chunk.
 */
export function preloadPagesWhenIdle(): void {
    const preload = () => {
        for (const load of Object.values(pages)) {
            void load();
        }
    };

    const preloadWhenIdle = () => {
        // Safari has no requestIdleCallback.
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(preload);
        } else {
            window.setTimeout(preload, 1000);
        }
    };

    if (document.readyState === 'complete') {
        preloadWhenIdle();
    } else {
        window.addEventListener('load', preloadWhenIdle, { once: true });
    }
}
