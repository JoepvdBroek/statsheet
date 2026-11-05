import type { ComponentProps, MouseEvent, ReactNode } from 'react';
import { QuietSpinner } from '@/components/quiet-spinner';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * A button that locks while the request it started runs, such as Start Workout or a save: it looks disabled, shows
 * a spinner in place of its icon, and ignores presses, so a double tap can't fire twice. Unlike a disabled button it
 * keeps focus, and it's read as busy.
 */
export function LockedButton({
    locked,
    icon,
    onClick,
    className,
    children,
    ...props
}: Omit<ComponentProps<typeof Button>, 'asChild' | 'disabled'> & {
    /** Whether the button's request is running. */
    locked: boolean;
    /** Shown before the label, and swapped for the spinner while locked. */
    icon?: ReactNode;
}) {
    const ignoreWhileLocked = (event: MouseEvent<HTMLButtonElement>) => {
        if (locked) {
            event.preventDefault();

            return;
        }

        onClick?.(event);
    };

    return (
        <Button
            {...props}
            aria-disabled={locked || undefined}
            aria-busy={locked || undefined}
            onClick={ignoreWhileLocked}
            className={cn(
                locked && 'pointer-events-none opacity-50',
                className,
            )}
        >
            {locked ? <QuietSpinner /> : icon}
            {children}
        </Button>
    );
}
