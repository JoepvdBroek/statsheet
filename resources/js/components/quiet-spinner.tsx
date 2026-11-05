import { Loader2Icon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A spinner for work whose state is told some other way, such as by the aria-busy of a locked button or of dimmed
 * content, so it's hidden from screen readers rather than a "Loading" status like Spinner.
 * It keeps spinning with reduced motion: its motion is the only sign of the work.
 */
export function QuietSpinner({ className }: { className?: string }) {
    return (
        <Loader2Icon
            aria-hidden="true"
            className={cn('size-4 animate-spin', className)}
        />
    );
}
