import * as React from 'react';

import { repeat, SkeletonBlock } from '@/components/page-skeleton';
import { PRBadge } from '@/components/statsheet/pr-badge';
import { StatBlock } from '@/components/statsheet/stat-block';
import { cn, formatKg } from '@/lib/utils';
import type { PersonalRecords } from '@/types';

/** A Set's weight as written for its Exercise: kg, or the added load on top of Bodyweight ("BW+10", "BW") for a Bodyweight Exercise. */
function formatLoad(weight: number, bodyweight: boolean): string {
    if (!bodyweight) {
        return formatKg(weight);
    }

    return weight ? `BW+${formatKg(weight)}` : 'BW';
}

type PersonalRecordsCardProps = Omit<
    React.ComponentProps<'section'>,
    'children'
> & {
    /** Undefined while they load: grey values under the real labels. */
    records?: PersonalRecords;
    /** For a Bodyweight Exercise, heaviest and reps-at-weight show the added load. */
    bodyweight?: boolean;
};

/** An Exercise's Personal Records: heaviest, Est. 1RM and best set, then the most reps at each weight. */
function PersonalRecordsCard({
    records,
    bodyweight = false,
    className,
    ...props
}: PersonalRecordsCardProps) {
    const headingId = React.useId();

    return (
        <section
            data-slot="personal-records-card"
            aria-labelledby={headingId}
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground',
                className,
            )}
            {...props}
        >
            <h2
                id={headingId}
                className="flex items-center gap-2 text-base font-medium"
            >
                <PRBadge />
                Personal Records
            </h2>
            {records === undefined ? (
                <PersonalRecordsSkeleton />
            ) : records.heaviest === null ? (
                <p className="text-sm text-muted-foreground">
                    No done working Sets yet. Log one to set your first records.
                </p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <StatBlock
                            size="sm"
                            tone="plain"
                            label="Heaviest"
                            value={formatLoad(records.heaviest, bodyweight)}
                            unit={
                                bodyweight && !records.heaviest
                                    ? undefined
                                    : 'kg'
                            }
                        />
                        <StatBlock
                            size="sm"
                            tone="plain"
                            label="Est. 1RM"
                            value={
                                records.e1rm === null
                                    ? '–'
                                    : formatKg(records.e1rm)
                            }
                            unit={records.e1rm === null ? undefined : 'kg'}
                        />
                        <StatBlock
                            size="sm"
                            tone="plain"
                            label="Best set"
                            value={formatKg(records.tonnage ?? 0)}
                            unit="kg"
                        />
                    </div>
                    <MostRepsAtWeight>
                        <ul className="flex flex-wrap gap-1.5">
                            {records.reps_at_weight.map((best) => (
                                <li
                                    key={best.weight}
                                    className="inline-flex h-7 items-center rounded-full bg-secondary px-2.5 text-sm tabular-nums"
                                >
                                    {best.reps} ×{' '}
                                    {formatLoad(best.weight, bodyweight)}
                                </li>
                            ))}
                        </ul>
                    </MostRepsAtWeight>
                </>
            )}
        </section>
    );
}

/** The records while they load: real labels with grey values and reps-at-weight chips. */
function PersonalRecordsSkeleton() {
    return (
        <>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                {['Heaviest', 'Est. 1RM', 'Best set'].map((label) => (
                    <StatBlock
                        key={label}
                        size="sm"
                        tone="plain"
                        label={label}
                        value={<SkeletonBlock className="my-1 h-6 w-16" />}
                    />
                ))}
            </div>
            <MostRepsAtWeight>
                <div className="flex flex-wrap gap-1.5">
                    {repeat(4, (index) => (
                        <SkeletonBlock
                            key={index}
                            className="h-7 w-20 rounded-full"
                        />
                    ))}
                </div>
            </MostRepsAtWeight>
        </>
    );
}

function MostRepsAtWeight({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-2">
            <h3 className="text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase">
                Most reps at a weight
            </h3>
            {children}
        </div>
    );
}

export { formatLoad, PersonalRecordsCard };
export type { PersonalRecordsCardProps };
