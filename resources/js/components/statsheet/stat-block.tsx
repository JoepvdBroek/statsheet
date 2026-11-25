import * as React from 'react';

import { cn } from '@/lib/utils';

type StatBlockProps = React.ComponentProps<'div'> & {
    /** The number, already formatted ("12,400", "102.5"). */
    value: React.ReactNode;
    /** Unit after the number ("kg", "reps"). */
    unit?: React.ReactNode;
    /** Eyebrow label under the number ("Volume this week"). */
    label: React.ReactNode;
    /** Change against the previous period, pre-formatted ("+4%", "−2.5 kg"). */
    delta?: React.ReactNode;
    /** Direction of the change; up is shown with ▲, down with ▼. */
    trend?: 'up' | 'down' | 'flat';
    /** Quieter line under the label, usually a StatBlockDetail. */
    detail?: React.ReactNode;
    /** lg: 48px hero number; sm: 24px, for inside cards and rows. */
    size?: 'lg' | 'sm';
    /** Neon for the one live number on a screen; plain white otherwise. */
    tone?: 'live' | 'plain';
};

function StatBlock({
    value,
    unit,
    label,
    delta,
    trend = 'flat',
    detail,
    size = 'lg',
    tone = 'live',
    className,
    ...props
}: StatBlockProps) {
    return (
        <div
            data-slot="stat-block"
            className={cn('flex min-w-0 flex-col gap-1', className)}
            {...props}
        >
            <div className="flex items-baseline gap-1.5">
                <span
                    data-slot="stat-block-value"
                    className={cn(
                        'font-medium tabular-nums',
                        size === 'lg'
                            ? 'text-5xl leading-[1.12]'
                            : 'text-2xl leading-[1.4]',
                        tone === 'live' ? 'text-primary' : 'text-foreground',
                    )}
                >
                    {value}
                </span>
                {unit ? (
                    <span
                        className={cn(
                            'text-muted-foreground',
                            size === 'lg' ? 'text-base' : 'text-sm',
                        )}
                    >
                        {unit}
                    </span>
                ) : null}
            </div>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase">
                    {label}
                </span>
                {delta ? (
                    <span className="text-xs text-foreground tabular-nums">
                        {trend === 'up' ? '▲ ' : trend === 'down' ? '▼ ' : ''}
                        {delta}
                    </span>
                ) : null}
            </div>
            {detail ? (
                <div className="text-xs text-muted-foreground tabular-nums">
                    {detail}
                </div>
            ) : null}
        </div>
    );
}

/** Short facts on one line, separated by dots; screen readers hear one phrase with commas. */
function StatBlockDetail({ parts }: { parts: string[] }) {
    return (
        <>
            <span aria-hidden="true">{parts.join(' · ')}</span>
            <span className="sr-only">{parts.join(', ')}</span>
        </>
    );
}

export { StatBlock, StatBlockDetail };
export type { StatBlockProps };
