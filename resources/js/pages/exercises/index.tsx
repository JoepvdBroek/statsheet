import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { PlusIcon, SearchIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import { nativeSelectClassName } from '@/components/exercise-form';
import Heading from '@/components/heading';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { capitalize, cn } from '@/lib/utils';
import { index } from '@/routes/exercises';
import type { Exercise } from '@/types';

type Filters = {
    search: string | null;
    muscle: string | null;
    equipment: string | null;
    archived: boolean;
};

function applyFilters(filters: Filters) {
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
        },
    );
}

export default function ExercisesIndex({
    exercises,
    filters,
    muscles,
    equipment,
}: {
    exercises: { data: Exercise[] };
    filters: Filters;
    muscles: string[];
    equipment: string[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        if (search.trim() === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () => applyFilters({ ...filters, search: search.trim() || null }),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters]);

    return (
        <>
            <Head title="Exercises" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Exercises"
                        description="Search by name, filter by Muscle or equipment."
                    />
                    <Button asChild>
                        <Link href={ExerciseController.create()}>
                            <PlusIcon aria-hidden="true" />
                            New
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                    <div className="relative">
                        <SearchIcon
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            type="search"
                            aria-label="Search Exercises by name"
                            placeholder="Search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <select
                        aria-label="Filter by Muscle"
                        className={nativeSelectClassName}
                        value={filters.muscle ?? ''}
                        onChange={(event) =>
                            applyFilters({
                                ...filters,
                                muscle: event.target.value || null,
                            })
                        }
                    >
                        <option value="">All Muscles</option>
                        {muscles.map((muscle) => (
                            <option key={muscle} value={muscle}>
                                {capitalize(muscle)}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter by equipment"
                        className={nativeSelectClassName}
                        value={filters.equipment ?? ''}
                        onChange={(event) =>
                            applyFilters({
                                ...filters,
                                equipment: event.target.value || null,
                            })
                        }
                    >
                        <option value="">All equipment</option>
                        {equipment.map((value) => (
                            <option key={value} value={value}>
                                {capitalize(value)}
                            </option>
                        ))}
                    </select>
                </div>

                <div
                    role="group"
                    aria-label="Show"
                    className="flex gap-1 self-start rounded-full border p-1"
                >
                    {[
                        { archived: false, title: 'In use' },
                        { archived: true, title: 'Archived' },
                    ].map((option) => (
                        <button
                            key={option.title}
                            type="button"
                            aria-pressed={filters.archived === option.archived}
                            onClick={() =>
                                applyFilters({
                                    ...filters,
                                    archived: option.archived,
                                })
                            }
                            className={cn(
                                'h-8 rounded-full px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                filters.archived === option.archived
                                    ? 'bg-secondary text-secondary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.title}
                        </button>
                    ))}
                </div>

                {exercises.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {filters.archived
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
            </div>
        </>
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
    breadcrumbs: [
        {
            title: 'Exercises',
            href: index(),
        },
    ],
};
