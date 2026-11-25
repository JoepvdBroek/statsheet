import * as React from 'react';

import { cn } from '@/lib/utils';

type SupersetGroupProps = React.ComponentProps<'section'> & {
    /** The Superset's letter ("A"), in order of the Supersets in the Routine or Workout. */
    letter: string;
};

/**
 * A frame around the consecutive ExerciseCards of one Superset, in a Routine or a Workout:
 * a neon bar along their left edge, a "Superset A" eyebrow, and tighter gaps between the cards than between Exercises.
 */
function SupersetGroup({
    letter,
    className,
    children,
    ...props
}: SupersetGroupProps) {
    const labelId = React.useId();

    return (
        <section
            data-slot="superset-group"
            aria-labelledby={labelId}
            className={cn('relative flex flex-col gap-1.5 pl-2.5', className)}
            {...props}
        >
            <span
                aria-hidden="true"
                className="absolute inset-y-0 left-0 w-0.5 rounded-full bg-primary/50"
            />
            <p
                id={labelId}
                className="px-1 text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase"
            >
                Superset <span className="text-primary">{letter}</span>
            </p>
            {children}
        </section>
    );
}

export { SupersetGroup };
export type { SupersetGroupProps };
