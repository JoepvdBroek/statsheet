import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { SkeletonBlock } from '@/components/page-content';
import type { ParentScreen } from '@/types';

/**
 * A large title on a tab screen. On a detail screen, a back chevron to its fixed parent screen above the title.
 * Actions, if any, sit beside the title.
 * A title that comes from props still loading is a grey block, read to screen readers as its `loadingTitle`.
 */
export function PageHeader({
    title,
    loadingTitle,
    parent,
    actions,
}: {
    title?: string;
    loadingTitle?: string;
    parent?: ParentScreen;
    actions?: ReactNode;
}) {
    const hasTitle = Boolean(title || loadingTitle);

    if (!hasTitle && !parent) {
        return null;
    }

    return (
        <header className="flex flex-col gap-1 px-4 pt-[calc(env(safe-area-inset-top)+1.5rem)]">
            {parent ? (
                <div className="flex items-center gap-1">
                    <Link
                        href={parent.href}
                        component={parent.component}
                        aria-label={`Back to ${parent.title}`}
                        className="-ml-2.5 inline-flex size-11 items-center justify-center rounded-full text-foreground outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <ChevronLeft
                            aria-hidden="true"
                            className="size-[22px]"
                        />
                    </Link>
                    <span className="text-sm text-muted-foreground">
                        {parent.title}
                    </span>
                </div>
            ) : null}
            {hasTitle || actions ? (
                <div className="flex items-center justify-between gap-3">
                    {hasTitle ? (
                        <h1
                            tabIndex={-1}
                            className="text-2xl leading-[1.4] font-normal tracking-[0.04em] outline-none"
                        >
                            {title || (
                                <>
                                    <span className="sr-only">
                                        {loadingTitle}
                                    </span>
                                    <SkeletonBlock className="my-0.5 h-7 w-48" />
                                </>
                            )}
                        </h1>
                    ) : null}
                    {actions ? (
                        <div className="flex shrink-0 items-center gap-1">
                            {actions}
                        </div>
                    ) : null}
                </div>
            ) : null}
            {loadingTitle ? <LoadedTitleAnnouncement title={title} /> : null}
        </header>
    );
}

/**
 * Politely announces a title once it arrives after its page drew without it.
 * A title there from the start isn't announced: the page change itself reads it.
 */
function LoadedTitleAnnouncement({ title }: { title?: string }) {
    const [awaited, setAwaited] = useState(!title);

    if (!title && !awaited) {
        setAwaited(true);
    }

    return (
        <span aria-live="polite" className="sr-only">
            {awaited ? title : null}
        </span>
    );
}
