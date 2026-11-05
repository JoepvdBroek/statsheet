import { Head } from '@inertiajs/react';
import { PageContent, PageError } from '@/components/page-content';
import type { PageShell } from '@/types';

/**
 * The page the server answers a failed Inertia visit with in production (AppServiceProvider). It isn't drawn on its
 * own: lib/failed-visits keeps the page you're on and shows that page's in-page error state instead. Should that ever
 * be bypassed, this shows the same error state.
 */
export default function ErrorPage({
    status,
}: {
    /** The failure's status; undefined while an instant visit loads the page, as Retry does. */
    status?: number;
}) {
    return (
        <>
            <Head title="Error" />

            <PageContent loading={status === undefined} className="p-4">
                {status === undefined ? null : (
                    <PageError failure={{ kind: 'status', status }} />
                )}
            </PageContent>
        </>
    );
}

ErrorPage.layout = {
    tab: null,
    title: 'Error',
} satisfies PageShell;
