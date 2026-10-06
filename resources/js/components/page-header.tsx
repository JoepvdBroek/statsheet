import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { ParentScreen } from '@/types';

/**
 * A large title on a tab screen. On a detail screen, a back chevron to its fixed parent screen above the title.
 */
export function PageHeader({
    title,
    parent,
}: {
    title?: string;
    parent?: ParentScreen;
}) {
    if (!title && !parent) {
        return null;
    }

    return (
        <header className="flex flex-col gap-1 px-4 pt-[calc(env(safe-area-inset-top)+1.5rem)]">
            {parent ? (
                <div className="flex items-center gap-1">
                    <Link
                        href={parent.href}
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
            {title ? (
                <h1 className="text-2xl leading-[1.4] font-normal tracking-[0.04em]">
                    {title}
                </h1>
            ) : null}
        </header>
    );
}
