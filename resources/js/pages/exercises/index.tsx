import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import { nativeSelectClassName } from '@/components/exercise-form';
import { PageContent } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { repeat, SkeletonBlock } from '@/components/page-skeleton';
import { QuietSpinner } from '@/components/quiet-spinner';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UpdatingContent, UpdatingSelect } from '@/components/updating-content';
import { useSamePageReload } from '@/hooks/use-same-page-reload';
import type { SamePageReloadOptions } from '@/hooks/use-same-page-reload';
import { parentScreens } from '@/lib/parent-screens';
import { capitalize, cn } from '@/lib/utils';
import { index } from '@/routes/exercises';
import type { Exercise, PageShell } from '@/types';

type Filters = {
    search: string | null;
    muscle: string | null;
    equipment: string | null;
    archived: boolean;
};

/** The filter fields, each showing a spinner while a slow reload it started runs. */
type FilterField = 'search' | 'muscle' | 'equipment' | 'in-use' | 'archived';

function applyFilters(
    filters: Filters,
    reloadOptions: ReturnType<SamePageReloadOptions<FilterField>>,
) {
    router.get(
        index.url(),
        {
            ...(filters.search ? { search: filters.search } : {}),
            ...(filters.muscle ? { muscle: filters.muscle } : {}),
            ...(filters.equipment ? { equipment: filters.equipment } : {}),
            ...(filters.archived ? { archived: 1 } : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['exercises', 'filters'],
            reset: ['exercises'],
            ...reloadOptions,
        },
    );
}

export default function ExercisesIndex({
    exercises,
    filters,
    muscles,
    equipment,
}: {
    /** Every prop is undefined while an instant visit loads the page. */
    exercises?: { data: Exercise[] };
    filters?: Filters;
    muscles?: string[];
    equipment?: string[];
}) {
    const { updatingField, reloadOptions } = useSamePageReload<FilterField>();

    return (
        <>
            <Head title="Exercises" />

            <PageContent
                loading={exercises === undefined}
                className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4"
            >
                <div className="flex items-start justify-between gap-4">
                    <PageDescription>
                        Search by name, filter by Muscle or equipment.
                    </PageDescription>
                    <Button asChild>
                        <Link
                            href={ExerciseController.create()}
                            component="exercises/create"
                        >
                            <PlusIcon aria-hidden="true" />
                            New
                        </Link>
                    </Button>
                </div>

                {filters && muscles && equipment ? (
                    <ExerciseFilters
                        filters={filters}
                        muscles={muscles}
                        equipment={equipment}
                        updatingField={updatingField}
                        reloadOptions={reloadOptions}
                    />
                ) : (
                    <ExerciseFiltersSkeleton />
                )}

                {exercises === undefined ? (
                    <ul className="flex flex-col gap-2">
                        {repeat(6, (index) => (
                            <ExerciseRowSkeleton key={index} />
                        ))}
                    </ul>
                ) : (
                    <UpdatingContent updating={updatingField !== null}>
                        {exercises.data.length === 0 ? (
                            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                {filters?.archived
                                    ? 'No archived Exercises.'
                                    : 'No Exercises match. Try another search or filter.'}
                            </p>
                        ) : (
                            <InfiniteScroll
                                data="exercises"
                                onlyNext
                                as="ul"
                                className="flex flex-col gap-2"
                            >
                                {exercises.data.map((exercise) => (
                                    <ExerciseRow
                                        key={exercise.id}
                                        exercise={exercise}
                                    />
                                ))}
                            </InfiniteScroll>
                        )}
                    </UpdatingContent>
                )}
            </PageContent>
        </>
    );
}

/**
 * Search, Muscle and equipment filters, and In use or Archived. Mounted once the filters are known, as the search
 * starts from them. The field a slow reload was started from shows a spinner.
 */
function ExerciseFilters({
    filters,
    muscles,
    equipment,
    updatingField,
    reloadOptions,
}: {
    filters: Filters;
    muscles: string[];
    equipment: string[];
    updatingField: FilterField | null;
    reloadOptions: SamePageReloadOptions<FilterField>;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (search.trim() === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () =>
                applyFilters(
                    { ...filters, search: search.trim() || null },
                    reloadOptions('search'),
                ),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters, reloadOptions]);

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <div className="relative">
                    {updatingField === 'search' ? (
                        <QuietSpinner className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" />
                    ) : (
                        <SearchIcon
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                    )}
                    <Input
                        type="search"
                        aria-label="Search Exercises by name"
                        placeholder="Search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="pl-9"
                    />
                </div>
                <UpdatingSelect updating={updatingField === 'muscle'}>
                    <select
                        aria-label="Filter by Muscle"
                        className={nativeSelectClassName}
                        value={filters.muscle ?? ''}
                        onChange={(event) =>
                            applyFilters(
                                {
                                    ...filters,
                                    muscle: event.target.value || null,
                                },
                                reloadOptions('muscle'),
                            )
                        }
                    >
                        <option value="">All Muscles</option>
                        {muscles.map((muscle) => (
                            <option key={muscle} value={muscle}>
                                {capitalize(muscle)}
                            </option>
                        ))}
                    </select>
                </UpdatingSelect>
                <UpdatingSelect updating={updatingField === 'equipment'}>
                    <select
                        aria-label="Filter by equipment"
                        className={nativeSelectClassName}
                        value={filters.equipment ?? ''}
                        onChange={(event) =>
                            applyFilters(
                                {
                                    ...filters,
                                    equipment: event.target.value || null,
                                },
                                reloadOptions('equipment'),
                            )
                        }
                    >
                        <option value="">All equipment</option>
                        {equipment.map((value) => (
                            <option key={value} value={value}>
                                {capitalize(value)}
                            </option>
                        ))}
                    </select>
                </UpdatingSelect>
            </div>

            <div
                role="group"
                aria-label="Show"
                className="flex gap-1 self-start rounded-full border p-1"
            >
                {(
                    [
                        { archived: false, title: 'In use', field: 'in-use' },
                        {
                            archived: true,
                            title: 'Archived',
                            field: 'archived',
                        },
                    ] as const
                ).map((option) => (
                    <button
                        key={option.title}
                        type="button"
                        aria-pressed={filters.archived === option.archived}
                        onClick={() =>
                            applyFilters(
                                { ...filters, archived: option.archived },
                                reloadOptions(option.field),
                            )
                        }
                        className={cn(
                            'flex h-8 items-center gap-1.5 rounded-full px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            filters.archived === option.archived
                                ? 'bg-secondary text-secondary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {updatingField === option.field ? (
                            <QuietSpinner />
                        ) : null}
                        {option.title}
                    </button>
                ))}
            </div>
        </>
    );
}

function ExerciseFiltersSkeleton() {
    return (
        <>
            <div className="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <SkeletonBlock className="h-9" />
                <SkeletonBlock className="h-9 sm:w-36" />
                <SkeletonBlock className="h-9 sm:w-36" />
            </div>

            <div className="flex gap-1 self-start rounded-full border p-1">
                {['In use', 'Archived'].map((title) => (
                    <span
                        key={title}
                        className="flex h-8 items-center rounded-full px-3 text-sm text-muted-foreground"
                    >
                        {title}
                    </span>
                ))}
            </div>
        </>
    );
}

function ExerciseRowSkeleton() {
    return (
        <li className="flex flex-col gap-2 rounded-xl border bg-card px-4 py-3 text-card-foreground">
            <SkeletonBlock className="my-0.5 h-5 w-40" />
            <div className="flex gap-1.5">
                {repeat(2, (index) => (
                    <SkeletonBlock
                        key={index}
                        className="h-6 w-16 rounded-full"
                    />
                ))}
            </div>
        </li>
    );
}

function ExerciseRow({ exercise }: { exercise: Exercise }) {
    const details = [
        exercise.equipment ? capitalize(exercise.equipment) : null,
        exercise.is_bodyweight ? 'Bodyweight' : null,
    ].filter(Boolean);

    return (
        <li className="flex items-start gap-3 rounded-xl border bg-card px-4 py-3 text-card-foreground">
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <Link
                        href={ExerciseController.show(exercise.id)}
                        component="exercises/show"
                        className={cn(
                            'truncate rounded-sm font-medium underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            exercise.archived && 'text-muted-foreground',
                        )}
                    >
                        {exercise.name}
                    </Link>
                    {details.length ? (
                        <span className="text-sm text-muted-foreground">
                            {details.join(' · ')}
                        </span>
                    ) : null}
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {exercise.muscles.map((trained) => (
                        <MuscleTag
                            key={trained.muscle}
                            muscle={trained.muscle}
                            role={trained.role}
                        />
                    ))}
                </div>
            </div>
            {exercise.archived ? (
                <div className="flex shrink-0 items-center gap-2">
                    <Badge variant="outline">Archived</Badge>
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={ExerciseController.restore(exercise.id)}
                            as="button"
                            preserveScroll
                        >
                            Restore
                        </Link>
                    </Button>
                </div>
            ) : null}
        </li>
    );
}

ExercisesIndex.layout = {
    tab: 'stats',
    title: 'Exercises',
    parent: parentScreens.stats,
} satisfies PageShell;
