import { useForm } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import { CornerDownRightIcon, DumbbellIcon } from 'lucide-react';
import { useMemo } from 'react';
import ExercisePicker from '@/components/exercise-picker';
import InputError from '@/components/input-error';
import { ItemMenu } from '@/components/item-menu';
import { ExerciseCard } from '@/components/statsheet/exercise-card';
import { PlannedSetRow } from '@/components/statsheet/set-row';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Exercise, Routine, SetKind } from '@/types';

type PlannedSet = {
    /** Client-only identity, so React keeps rows apart while they move. */
    key: string;
    target_reps: number | null;
    target_weight: number | null;
    kind: SetKind;
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
                        kind: 'working',
                    },
                ],
            },
        ]);

    /**
     * A new Set repeats the last non-warm-up Set's Target, so a plan of 3 × 8 × 80 takes two taps.
     * After a Drop Set it is another Drop Set, otherwise a Working Set.
     */
    const addSet = (exerciseIndex: number) =>
        setSets(exerciseIndex, (sets) => {
            const last =
                sets.findLast((set) => set.kind !== 'warm_up') ?? sets.at(-1);

            return [
                ...sets,
                {
                    key: newKey(),
                    target_reps: last?.target_reps ?? null,
                    target_weight: last?.target_weight ?? null,
                    kind: last?.kind === 'drop' ? 'drop' : 'working',
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
                            kind: set.kind,
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
                                            (other) => other.kind === 'working',
                                        ).length;
                                    const followsCountingSet =
                                        setIndex > 0 &&
                                        planned.sets[setIndex - 1].kind !==
                                            'warm_up';

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
                                            kind={set.kind}
                                            onKindChange={(kind) =>
                                                updateSet(
                                                    exerciseIndex,
                                                    setIndex,
                                                    { kind },
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
                                                >
                                                    {set.kind === 'drop' ? (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                updateSet(
                                                                    exerciseIndex,
                                                                    setIndex,
                                                                    {
                                                                        kind: 'working',
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <DumbbellIcon aria-hidden="true" />
                                                            Make a working set
                                                        </DropdownMenuItem>
                                                    ) : followsCountingSet ? (
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                updateSet(
                                                                    exerciseIndex,
                                                                    setIndex,
                                                                    {
                                                                        kind: 'drop',
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <CornerDownRightIcon aria-hidden="true" />
                                                            Make a drop set
                                                        </DropdownMenuItem>
                                                    ) : null}
                                                </ItemMenu>
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

                <ExercisePicker
                    exercises={exercises}
                    onPick={addExercise}
                    description="Archived Exercises aren't offered. Restore one first to plan it."
                />
            </div>

            <Button type="submit" size="lg" disabled={form.processing}>
                {submitLabel}
            </Button>
        </form>
    );
}
