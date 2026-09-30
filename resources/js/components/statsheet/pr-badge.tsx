import { TrophyIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const labels = {
    heaviest: 'Heaviest',
    e1rm: 'Est. 1RM',
    reps: 'Most reps',
    tonnage: 'Best set',
} as const;

type PRBadgeProps = React.ComponentProps<'span'> & {
    /** Which Personal Record measure was beaten. Omit for a bare "PR". */
    kind?: keyof typeof labels;
    /** Optional value to show after the label ("110 kg"). */
    value?: React.ReactNode;
};

function PRBadge({ kind, value, className, ...props }: PRBadgeProps) {
    return (
        <span
            data-slot="pr-badge"
            className={cn(
                'inline-flex h-6 shrink-0 items-center gap-1 rounded-full bg-primary px-2.5 text-xs whitespace-nowrap text-primary-foreground [&>svg]:size-3.5',
                className,
            )}
            {...props}
        >
            <TrophyIcon aria-hidden="true" />
            <span>{kind ? `PR · ${labels[kind]}` : 'PR'}</span>
            {value ? <span className="tabular-nums">{value}</span> : null}
        </span>
    );
}

export { PRBadge };
export type { PRBadgeProps };
