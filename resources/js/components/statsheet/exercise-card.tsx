import { MoreHorizontalIcon, PlusIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { SetRowHeader } from '@/components/statsheet/set-row';

type ExerciseCardProps = React.ComponentProps<'section'> & {
    /** Exercise name, variation included ("Barbell bench press"). */
    name: string;
    /** Makes the name a link, e.g. to the Exercise's history. */
    href?: string;
    muscles?: { muscle: string; role?: 'primary' | 'secondary' }[];
    /** Done working sets, for the progress counter. */
    doneCount?: number;
    /** Working sets in total. */
    setCount?: number;
    bodyweight?: boolean;
    /** Shown under the header, e.g. "Last time: 8 × 80 · 8 × 80 · 7 × 80". */
    hint?: React.ReactNode;
    onAddSet?: () => void;
    onMore?: () => void;
    /** Hide the Add set button (read-only views). */
    readOnly?: boolean;
};

function ExerciseCard({
    name,
    href,
    muscles = [],
    doneCount,
    setCount,
    bodyweight = false,
    hint,
    onAddSet,
    onMore,
    readOnly = false,
    className,
    children,
    ...props
}: ExerciseCardProps) {
    const complete =
        setCount !== undefined && setCount > 0 && doneCount === setCount;
    return (
        <section
            data-slot="exercise-card"
            data-complete={complete}
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card px-3 py-4 text-card-foreground',
                className,
            )}
            {...props}
        >
            <header className="flex items-start gap-3 px-1">
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <h3 className="truncate text-base leading-[1.3] font-medium">
                        {href ? (
                            <a
                                href={href}
                                className="rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                {name}
                            </a>
                        ) : (
                            name
                        )}
                    </h3>
                    {muscles.length ? (
                        <div className="flex flex-wrap gap-1.5">
                            {muscles.map((m) => (
                                <MuscleTag
                                    key={m.muscle}
                                    muscle={m.muscle}
                                    role={m.role}
                                />
                            ))}
                        </div>
                    ) : null}
                </div>
                {setCount !== undefined ? (
                    <span
                        className={cn(
                            'shrink-0 pt-0.5 text-sm tabular-nums',
                            complete ? 'text-primary' : 'text-muted-foreground',
                        )}
                        aria-label={`${doneCount ?? 0} of ${setCount} sets done`}
                    >
                        {doneCount ?? 0}/{setCount}
                    </span>
                ) : null}
                {onMore ? (
                    <button
                        type="button"
                        aria-label={`More actions for ${name}`}
                        onClick={onMore}
                        className="-mt-1 -mr-1 flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>svg]:size-4"
                    >
                        <MoreHorizontalIcon aria-hidden="true" />
                    </button>
                ) : null}
            </header>
            {hint ? (
                <p className="px-1 text-sm text-muted-foreground tabular-nums">
                    {hint}
                </p>
            ) : null}
            <div
                role="table"
                aria-label={`${name} sets`}
                className="flex flex-col gap-1"
            >
                <SetRowHeader bodyweight={bodyweight} />
                {children}
            </div>
            {!readOnly && onAddSet ? (
                <button
                    type="button"
                    onClick={onAddSet}
                    className="flex h-10 items-center justify-center gap-2 rounded-full text-sm text-foreground outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>svg]:size-4"
                >
                    <PlusIcon aria-hidden="true" />
                    Add set
                </button>
            ) : null}
        </section>
    );
}

export { ExerciseCard };
export type { ExerciseCardProps };
