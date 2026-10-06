import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/** A short line under the page title saying what the screen is for. */
export function PageDescription({ className, ...props }: ComponentProps<'p'>) {
    return (
        <p
            className={cn('text-sm text-muted-foreground', className)}
            {...props}
        />
    );
}
