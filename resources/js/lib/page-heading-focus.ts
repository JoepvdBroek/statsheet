import { router } from '@inertiajs/react';

/**
 * Moves focus to the new page's h1 on every page change, so a screen reader reads the new title and keyboard focus
 * starts at the top. That's at tap for an instant visit, whose page draws itself before its props arrive, and on
 * back/forward for the restored page. The first page load and same-page reloads (which replace the history entry,
 * like a search) leave focus alone. Only an h1 with `tabIndex={-1}` takes focus, as PageHeader's does; the auth
 * pages' h1 doesn't, so their autofocused field keeps it. Returns a function that stops listening.
 */
export function focusHeadingOnPageChange(): () => void {
    return router.on('navigate', (event) => {
        if (event.detail.type === 'initial') {
            return;
        }

        document.querySelector<HTMLElement>('h1')?.focus({
            preventScroll: true,
        });
    });
}
