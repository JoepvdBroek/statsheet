import { Head, Link, router } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import RoutineController from '@/actions/App/Http/Controllers/RoutineController';
import Heading from '@/components/heading';
import { RoutineCard } from '@/components/statsheet/routine-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index } from '@/routes/routines';
import type { Routine } from '@/types';

/** Primary Muscles the Routine trains, the most planned working Sets first. */
function primaryMuscles(routine: Routine): string[] {
    const workingSets = new Map<string, number>();

    for (const planned of routine.exercises) {
        const count = planned.sets.filter((set) => !set.is_warm_up).length;

        for (const trained of planned.exercise.muscles) {
            if (trained.role === 'primary') {
                workingSets.set(
                    trained.muscle,
                    (workingSets.get(trained.muscle) ?? 0) + count,
                );
            }
        }
    }

    return [...workingSets.entries()]
        .sort(([, a], [, b]) => b - a)
        .map(([muscle]) => muscle);
}

export default function RoutinesIndex({
    routines,
    archived,
}: {
    routines: Routine[];
    archived: boolean;
}) {
    return (
        <>
            <Head title="Routines" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Routines"
                        description="Reusable plans to start a Workout from."
                    />
                    <Button asChild>
                        <Link href={RoutineController.create()}>
                            <PlusIcon aria-hidden="true" />
                            New
                        </Link>
                    </Button>
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
                        <Link
                            key={option.title}
                            href={index({
                                query: option.archived ? { archived: 1 } : {},
                            })}
                            aria-pressed={archived === option.archived}
                            preserveScroll
                            className={cn(
                                'flex h-8 items-center rounded-full px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                archived === option.archived
                                    ? 'bg-secondary text-secondary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.title}
                        </Link>
                    ))}
                </div>

                {routines.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {archived
                            ? 'No archived Routines.'
                            : 'No Routines yet. Plan your first one.'}
                    </p>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {routines.map((routine) => (
                            <RoutineCard
                                key={routine.id}
                                name={routine.name}
                                exerciseCount={routine.exercises.length}
                                setCount={routine.exercises.reduce(
                                    (total, planned) =>
                                        total + planned.sets.length,
                                    0,
                                )}
                                muscles={primaryMuscles(routine)}
                                archived={routine.archived}
                                onStart={() =>
                                    router.visit(
                                        RoutineController.start(routine.id),
                                    )
                                }
                                onEdit={() =>
                                    router.visit(
                                        RoutineController.edit(routine.id),
                                    )
                                }
                                onRestore={() =>
                                    router.visit(
                                        RoutineController.restore(routine.id),
                                        { preserveScroll: true },
                                    )
                                }
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

RoutinesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Routines',
            href: index(),
        },
    ],
};
