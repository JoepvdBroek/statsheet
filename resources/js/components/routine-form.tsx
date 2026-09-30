import { useForm } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import {
    ArrowDownIcon,
    ArrowUpIcon,
    MoreHorizontalIcon,
    PlusIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { ExerciseCard } from '@/components/statsheet/exercise-card';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { PlannedSetRow } from '@/components/statsheet/set-row';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import type { Exercise, Routine } from '@/types';

type PlannedSet = {
    /** Client-only identity, so React keeps rows apart while they move. */
    key: string;
    target_reps: number | null;
    target_weight: number | null;
    is_warm_up: boolean;
};

type PlannedExercise = {
    key: string;
    exercise_id: number;
    sets: PlannedSet[];
};

type RoutineFormData = {
    name: string;
    exercises: PlannedExercise[];
};

const newKey = () => crypto.randomUUID();

/** Moves the item at `from` one place up (-1) or down (+1). */
function move<T>(items: T[], from: number, offset: -1 | 1): T[] {
    const to = from + offset;

    if (to < 0 || to >= items.length) {
        return items;
    }

    const moved = [...items];
    [moved[from], moved[to]] = [moved[to], moved[from]];

    return moved;
}

export default function RoutineForm({
    routine,
    exercises,
    action,
    submitLabel,
}: {
    routine?: Routine;
    /** The owner's Exercises in use to pick from; undefined while the deferred prop loads. */
    exercises?: Exercise[];
    action: UrlMethodPair;
    submitLabel: string;
}) {
    const form = useForm<RoutineFormData>({
        name: routine?.name ?? '',
        exercises: (routine?.exercises ?? []).map((planned) => ({
            key: newKey(),
            exercise_id: planned.exercise.id,
            sets: planned.sets.map((set) => ({ key: newKey(), ...set })),
        })),
    });

    /** Planned Exercises stay known even when archived, so they aren't in the picker's list. */
    const exercisesById = useMemo(
        () =>
            new Map(
                [
                    ...(routine?.exercises ?? []).map(
                        (planned) => planned.exercise,
                    ),
                    ...(exercises ?? []),
                ].map((exercise) => [exercise.id, exercise]),
            ),
        [routine, exercises],
    );

    const errors = form.errors as Record<string, string | undefined>;
    const errorsFor = (prefix: string) => [
        ...new Set(
            Object.entries(errors)
                .filter(([key]) => key.startsWith(prefix))
                .map(([, message]) => message)
                .filter((message): message is string => Boolean(message)),
        ),
    ];

    const setExercises = (
        update: (planned: PlannedExercise[]) => PlannedExercise[],
    ) =>
        form.setData((data) => ({
            ...data,
            exercises: update(data.exercises),
        }));

    const setSets = (
        exerciseIndex: number,
        update: (sets: PlannedSet[]) => PlannedSet[],
    ) =>
        setExercises((planned) =>
            planned.map((exercise, index) =>
                index === exerciseIndex
                    ? { ...exercise, sets: update(exercise.sets) }
                    : exercise,
            ),
        );

    const updateSet = (
        exerciseIndex: number,
        setIndex: number,
        changes: Partial<PlannedSet>,
    ) =>
        setSets(exerciseIndex, (sets) =>
            sets.map((set, index) =>
                index === setIndex ? { ...set, ...changes } : set,
            ),
        );

    const addExercise = (exercise: Exercise) =>
        setExercises((planned) => [
            ...planned,
            {
                key: newKey(),
                exercise_id: exercise.id,
                sets: [
                    {
                        key: newKey(),
                        target_reps: null,
                        target_weight: null,
                        is_warm_up: false,
                    },
                ],
            },
        ]);

    /** A new Set repeats the last working Set's Target, so a plan of 3 × 8 × 80 takes two taps. */
    const addSet = (exerciseIndex: number) =>
        setSets(exerciseIndex, (sets) => {
            const last = sets.findLast((set) => !set.is_warm_up) ?? sets.at(-1);

            return [
                ...sets,
                {
                    key: newKey(),
                    target_reps: last?.target_reps ?? null,
                    target_weight: last?.target_weight ?? null,
                    is_warm_up: false,
                },
            ];
        });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    name: data.name,
                    exercises: data.exercises.map((planned) => ({
                        exercise_id: planned.exercise_id,
                        sets: planned.sets.map((set) => ({
                            target_reps: set.target_reps,
                            target_weight: set.target_weight,
                            is_warm_up: set.is_warm_up,
                        })),
                    })),
                }));
                form.submit(action, { preserveScroll: true });
            }}
            className="space-y-6"
        >
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    required
                    placeholder="e.g. Push day"
                    aria-invalid={errors.name ? true : undefined}
                />
                <InputError message={errors.name} />
            </div>

            <div className="flex flex-col gap-3">
                {form.data.exercises.map((planned, exerciseIndex) => {
                    const exercise = exercisesById.get(planned.exercise_id);

                    return (
                        <div key={planned.key} className="flex flex-col gap-2">
                            <ExerciseCard
                                planning
                                name={exercise?.name ?? 'Exercise'}
                                muscles={exercise?.muscles}
                                bodyweight={exercise?.is_bodyweight}
                                hint={
                                    exercise?.archived
                                        ? "Archived. It stays in this Routine, but can't be added again once removed."
                                        : undefined
                                }
                                onAddSet={() => addSet(exerciseIndex)}
                                actions={
                                    <ItemMenu
                                        label={`More actions for ${exercise?.name ?? 'this Exercise'}`}
                                        removeLabel="Remove Exercise"
                                        isFirst={exerciseIndex === 0}
                                        isLast={
                                            exerciseIndex ===
                                            form.data.exercises.length - 1
                                        }
                                        onMove={(offset) =>
                                            setExercises((all) =>
                                                move(
                                                    all,
                                                    exerciseIndex,
                                                    offset,
                                                ),
                                            )
                                        }
                                        onRemove={() =>
                                            setExercises((all) =>
                                                all.filter(
                                                    (_, index) =>
                                                        index !== exerciseIndex,
                                                ),
                                            )
                                        }
                                    />
                                }
                            >
                                {planned.sets.map((set, setIndex) => {
                                    const field = `exercises.${exerciseIndex}.sets.${setIndex}`;
                                    const workingSetNumber = planned.sets
                                        .slice(0, setIndex + 1)
                                        .filter(
                                            (other) => !other.is_warm_up,
                                        ).length;

                                    return (
                                        <PlannedSetRow
                                            key={set.key}
                                            index={workingSetNumber}
                                            bodyweight={exercise?.is_bodyweight}
                                            target={{
                                                reps: set.target_reps,
                                                weight: set.target_weight,
                                            }}
                                            onTargetChange={(target) =>
                                                updateSet(
                                                    exerciseIndex,
                                                    setIndex,
                                                    {
                                                        target_reps:
                                                            target.reps,
                                                        target_weight:
                                                            target.weight,
                                                    },
                                                )
                                            }
                                            warmup={set.is_warm_up}
                                            onWarmupChange={(isWarmUp) =>
                                                updateSet(
                                                    exerciseIndex,
                                                    setIndex,
                                                    {
                                                        is_warm_up: isWarmUp,
                                                    },
                                                )
                                            }
                                            invalid={{
                                                reps: Boolean(
                                                    errors[
                                                        `${field}.target_reps`
                                                    ],
                                                ),
                                                weight: Boolean(
                                                    errors[
                                                        `${field}.target_weight`
                                                    ],
                                                ),
                                            }}
                                            actions={
                                                <ItemMenu
                                                    label={`More actions for set ${setIndex + 1}`}
                                                    removeLabel="Remove set"
                                                    isFirst={setIndex === 0}
                                                    isLast={
                                                        setIndex ===
                                                        planned.sets.length - 1
                                                    }
                                                    onMove={(offset) =>
                                                        setSets(
                                                            exerciseIndex,
                                                            (sets) =>
                                                                move(
                                                                    sets,
                                                                    setIndex,
                                                                    offset,
                                                                ),
                                                        )
                                                    }
                                                    onRemove={() =>
                                                        setSets(
                                                            exerciseIndex,
                                                            (sets) =>
                                                                sets.filter(
                                                                    (
                                                                        _,
                                                                        index,
                                                                    ) =>
                                                                        index !==
                                                                        setIndex,
                                                                ),
                                                        )
                                                    }
                                                />
                                            }
                                        />
                                    );
                                })}
                            </ExerciseCard>
                            {errorsFor(`exercises.${exerciseIndex}.`).map(
                                (message) => (
                                    <InputError
                                        key={message}
                                        message={message}
                                        className="px-1"
                                    />
                                ),
                            )}
                        </div>
                    );
                })}

                <ExercisePicker exercises={exercises} onPick={addExercise} />
            </div>

            <Button type="submit" size="lg" disabled={form.processing}>
                {submitLabel}
            </Button>
        </form>
    );
}

function ItemMenu({
    label,
    removeLabel,
    isFirst,
    isLast,
    onMove,
    onRemove,
}: {
    label: string;
    removeLabel: string;
    isFirst: boolean;
    isLast: boolean;
    onMove: (offset: -1 | 1) => void;
    onRemove: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    className="flex size-10 shrink-0 items-center justify-center rounded-full text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>svg]:size-4"
                >
                    <MoreHorizontalIcon aria-hidden="true" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem
                    disabled={isFirst}
                    onSelect={() => onMove(-1)}
                >
                    <ArrowUpIcon aria-hidden="true" />
                    Move up
                </DropdownMenuItem>
                <DropdownMenuItem disabled={isLast} onSelect={() => onMove(1)}>
                    <ArrowDownIcon aria-hidden="true" />
                    Move down
                </DropdownMenuItem>
                <DropdownMenuItem variant="destructive" onSelect={onRemove}>
                    <Trash2Icon aria-hidden="true" />
                    {removeLabel}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ExercisePicker({
    exercises,
    onPick,
}: {
    exercises?: Exercise[];
    onPick: (exercise: Exercise) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const query = search.trim().toLowerCase();
    const matches = (exercises ?? []).filter((exercise) =>
        exercise.name.toLowerCase().includes(query),
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                setOpen(isOpen);
                setSearch('');
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="lg">
                    <PlusIcon aria-hidden="true" />
                    Add Exercise
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[85dvh] flex-col">
                <DialogTitle>Add Exercise</DialogTitle>
                <DialogDescription>
                    Archived Exercises aren't offered. Restore one first to plan
                    it.
                </DialogDescription>

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

                <div className="-mx-2 min-h-0 flex-1 overflow-y-auto">
                    {exercises === undefined ? (
                        <div className="flex flex-col gap-2 px-2">
                            {[0, 1, 2, 3].map((row) => (
                                <Skeleton key={row} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : matches.length === 0 ? (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            No Exercises match.
                        </p>
                    ) : (
                        <ul className="flex flex-col">
                            {matches.map((exercise) => (
                                <li key={exercise.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onPick(exercise);
                                            setOpen(false);
                                            setSearch('');
                                        }}
                                        className="flex w-full flex-col items-start gap-1.5 rounded-lg px-2 py-2.5 text-left outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <span className="font-medium">
                                            {exercise.name}
                                        </span>
                                        <span className="flex flex-wrap gap-1.5">
                                            {exercise.muscles.map((trained) => (
                                                <MuscleTag
                                                    key={trained.muscle}
                                                    muscle={trained.muscle}
                                                    role={trained.role}
                                                />
                                            ))}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
