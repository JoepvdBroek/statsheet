import * as React from 'react';

import { cn } from '@/lib/utils';

type MuscleTagProps = React.ComponentProps<'span'> & {
    /** One of the 17 Muscles, written in lower case as in the glossary. */
    muscle: string;
    /** Primary Muscles count in full toward Volume; secondary count half. */
    role?: 'primary' | 'secondary';
};

function MuscleTag({
    muscle,
    role = 'primary',
    className,
    ...props
}: MuscleTagProps) {
    return (
        <span
            data-slot="muscle-tag"
            data-role={role}
            title={
                role === 'secondary'
                    ? `${muscle} (secondary, counts half)`
                    : muscle
            }
            className={cn(
                'inline-flex h-6 items-center rounded-full px-2.5 text-xs whitespace-nowrap',
                role === 'primary'
                    ? 'bg-secondary text-secondary-foreground'
                    : 'border border-border text-muted-foreground',
                className,
            )}
            {...props}
        >
            {muscle.charAt(0).toUpperCase() + muscle.slice(1)}
        </span>
    );
}

export { MuscleTag };
export type { MuscleTagProps };
