import type { InertiaLinkProps } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { PageComponent } from '@/types';

/**
 * The page component for a navigation link's instant visit, or undefined for a link to the page you're on: tapping
 * that one reloads the page, rather than swapping it for its own skeleton.
 */
export function useInstantVisitComponent(): (
    href: NonNullable<InertiaLinkProps['href']>,
    component: PageComponent,
) => PageComponent | undefined {
    const { isCurrentUrl } = useCurrentUrl();

    return (href, component) => (isCurrentUrl(href) ? undefined : component);
}
