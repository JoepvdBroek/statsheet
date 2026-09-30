import * as React from 'react';

import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { MuscleTag } from '@/components/statsheet/muscle-tag';

type RoutineCardProps = React.ComponentProps<'article'> & {
    name: string;
    exerciseCount: number;
    setCount?: number;
    /** Primary Muscles trained, most volume first. Up to four are shown. */
    muscles?: string[];
    /** Pre-formatted ("Monday", "12 days ago", "Never"). */
    lastPerformed?: string;
    archived?: boolean;
    onStart?: () => void;
    /** Render Start workout as a link instead of a button. */
    startHref?: string;
    /** Render Edit as a link instead of a button. */
    editHref?: string;
    onEdit?: () => void;
    onRestore?: () => void;
};

function RoutineCard({
    name,
    exerciseCount,
    setCount,
    muscles = [],
    lastPerformed,
    archived = false,
    onStart,
    startHref,
    editHref,
    onEdit,
    onRestore,
    className,
    ...props
}: RoutineCardProps) {
    const shown = muscles.slice(0, 4);
    const more = muscles.length - shown.length;
    return (
        <article
            data-slot="routine-card"
            data-archived={archived}
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-5 text-card-foreground',
                className,
            )}
            {...props}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <h3
                        className={cn(
                            'truncate text-base leading-[1.3] font-medium',
                            archived && 'text-muted-foreground',
                        )}
                    >
                        {name}
                    </h3>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {exerciseCount} exercises
                        {setCount !== undefined ? ` · ${setCount} sets` : ''}
                        {lastPerformed ? ` · ${lastPerformed}` : ''}
                    </p>
                </div>
                {archived ? <Badge variant="outline">Archived</Badge> : null}
            </div>
            {shown.length ? (
                <div className="flex flex-wrap gap-1.5">
                    {shown.map((m) => (
                        <MuscleTag key={m} muscle={m} />
                    ))}
                    {more > 0 ? (
                        <MuscleTag muscle={`+${more}`} role="secondary" />
                    ) : null}
                </div>
            ) : null}
            <div className="flex gap-2">
                {archived ? (
                    <Button variant="outline" size="sm" onClick={onRestore}>
                        Restore
                    </Button>
                ) : (
                    <>
                        {startHref ? (
                            <a
                                href={startHref}
                                className={buttonVariants({ size: 'sm' })}
                            >
                                Start workout
                            </a>
                        ) : (
                            <Button size="sm" onClick={onStart}>
                                Start workout
                            </Button>
                        )}
                        {editHref ? (
                            <a
                                href={editHref}
                                className={buttonVariants({
                                    variant: 'ghost',
                                    size: 'sm',
                                })}
                            >
                                Edit
                            </a>
                        ) : (
                            <Button variant="ghost" size="sm" onClick={onEdit}>
                                Edit
                            </Button>
                        )}
                    </>
                )}
            </div>
        </article>
    );
}

export { RoutineCard };
export type { RoutineCardProps };
