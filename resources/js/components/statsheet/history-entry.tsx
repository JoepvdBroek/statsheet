import { CornerDownRightIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';
import { PRBadge } from '@/components/statsheet/pr-badge';
import type { SetKind } from '@/types';

type HistorySet = {
    reps: number;
    weight: number;
    /** Warm-ups are shown dashed and never the top Set; a drop set gets a drop mark. Working when left out. */
    kind?: SetKind;
    /** The server's top Set of the Workout. When any Set carries it, it replaces the client-side `epley` pick. */
    top?: boolean;
};

type HistoryEntryProps = React.ComponentProps<'article'> & {
    /** Pre-formatted date ("Mon 22 Sep"). */
    date: string;
    /** The Workout it came from ("Push Day"), or "Empty workout". */
    workout?: string;
    sets: HistorySet[];
    /** Pre-formatted best Estimated 1RM of the session ("102.7"). */
    e1rm?: string;
    /** Pre-formatted Intensity of the session's heaviest Set ("86"), as a percentage of the Expected 1RM. */
    intensity?: string;
    /** Pre-formatted Average Weight of the session ("94"). */
    averageWeight?: string;
    /** Personal Records set in this session. */
    prs?: ('heaviest' | 'e1rm' | 'reps' | 'tonnage')[];
    bodyweight?: boolean;
};

function epley(s: HistorySet) {
    return s.reps <= 12 ? s.weight * (1 + s.reps / 30) : 0;
}

function HistoryEntry({
    date,
    workout,
    sets,
    e1rm,
    intensity,
    averageWeight,
    prs = [],
    bodyweight = false,
    className,
    ...props
}: HistoryEntryProps) {
    const working = sets.filter((s) => s.kind !== 'warm_up');
    const top = sets.some((s) => s.top !== undefined)
        ? (sets.find((s) => s.top) ?? null)
        : working.reduce<HistorySet | null>(
              (best, s) => (!best || epley(s) > epley(best) ? s : best),
              null,
          );
    return (
        <article
            data-slot="history-entry"
            className={cn(
                'flex flex-col gap-2 border-b py-4 last:border-b-0',
                className,
            )}
            {...props}
        >
            <div className="flex items-baseline justify-between gap-3">
                <div className="flex min-w-0 items-baseline gap-2">
                    <span className="text-sm font-medium whitespace-nowrap">
                        {date}
                    </span>
                    {workout ? (
                        <span className="truncate text-sm text-muted-foreground">
                            {workout}
                        </span>
                    ) : null}
                </div>
                {e1rm || intensity || averageWeight ? (
                    <span className="flex shrink-0 items-baseline gap-1.5 text-xs whitespace-nowrap text-muted-foreground tabular-nums">
                        {e1rm ? (
                            <span>
                                e1RM{' '}
                                <span className="text-sm text-foreground">
                                    {e1rm}
                                </span>
                            </span>
                        ) : null}
                        {intensity ? (
                            <span title="Intensity of the heaviest set">
                                <span className="text-sm text-foreground">
                                    {intensity}
                                </span>
                                %
                            </span>
                        ) : null}
                        {averageWeight ? (
                            <span title="Average Weight">
                                avg{' '}
                                <span className="text-sm text-foreground">
                                    {averageWeight}
                                </span>
                            </span>
                        ) : null}
                    </span>
                ) : null}
            </div>
            <ul className="flex flex-wrap gap-1.5" aria-label="Sets">
                {sets.map((s, i) => (
                    <li
                        key={i}
                        className={cn(
                            'inline-flex h-7 items-center gap-0.5 rounded-full px-2.5 text-sm tabular-nums',
                            s.kind === 'warm_up'
                                ? 'border border-dashed border-border text-muted-foreground'
                                : s === top
                                  ? 'bg-secondary text-foreground ring-1 ring-primary/60'
                                  : 'bg-secondary text-foreground',
                        )}
                        title={
                            s.kind === 'warm_up'
                                ? 'Warm-up'
                                : s === top
                                  ? 'Top set'
                                  : s.kind === 'drop'
                                    ? 'Drop set'
                                    : undefined
                        }
                    >
                        {s.kind === 'drop' ? (
                            <CornerDownRightIcon
                                aria-label="Drop set"
                                className="size-3 text-muted-foreground"
                            />
                        ) : null}
                        {s.reps} ×{' '}
                        {bodyweight
                            ? `BW${s.weight ? `+${s.weight}` : ''}`
                            : s.weight}
                    </li>
                ))}
            </ul>
            {prs.length ? (
                <div className="flex flex-wrap gap-1.5">
                    {prs.map((k) => (
                        <PRBadge key={k} kind={k} />
                    ))}
                </div>
            ) : null}
        </article>
    );
}

export { HistoryEntry, epley };
export type { HistoryEntryProps, HistorySet };
