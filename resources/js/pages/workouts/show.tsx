import { Form, Head, router, usePage } from '@inertiajs/react';
import type { VisitOptions } from '@inertiajs/core';
import { FlameIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import WorkoutExerciseController from '@/actions/App/Http/Controllers/WorkoutExerciseController';
import WorkoutSetController from '@/actions/App/Http/Controllers/WorkoutSetController';
import ExercisePicker from '@/components/exercise-picker';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ItemMenu, MoveRemoveItems } from '@/components/item-menu';
import { ExerciseCard } from '@/components/statsheet/exercise-card';
import { SetRow, meetsTarget } from '@/components/statsheet/set-row';
import type { SetValues } from '@/components/statsheet/set-row';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { cn, formatDateTime } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { Exercise, Workout, WorkoutExercise, WorkoutSet } from '@/types';

type Errors = Record<string, string | undefined>;

/** Structure changes: keep the owner's place on the page. */
const structureVisit = { preserveScroll: true } satisfies VisitOptions;

/** Logging runs in the background, so quick taps on several Sets never cancel each other. */
const loggingVisit = {
    preserveScroll: true,
    preserveState: true,
    async: true,
} satisfies VisitOptions;

/** The page's Workout with one Set changed, applied before the server answers. */
function withSetChanges(
    props: Record<string, unknown>,
    setId: number,
    changes: Partial<WorkoutSet>,
) {
    const workout = props.workout as Workout;

    return {
        workout: {
            ...workout,
            exercises: workout.exercises.map((performed) => ({
                ...performed,
                sets: performed.sets.map((set) =>
                    set.id === setId ? { ...set, ...changes } : set,
                ),
            })),
        },
    };
}

export default function ShowWorkout({
    workout,
    exercises,
}: {
    workout: Workout;
    /** The owner's Exercises in use to add; undefined while the deferred prop loads. */
    exercises?: Exercise[];
}) {
    const { auth, errors } = usePage<{ errors: Errors }>().props;
    const inProgress = workout.status === 'in_progress';
    const timeZone = auth.user.timezone;

    return (
        <>
            <Head title="Workout" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div>
                    <Heading
                        title={inProgress ? 'Workout in progress' : 'Workout'}
                        description={[
                            `Started ${formatDateTime(workout.started_at, timeZone)}`,
                            workout.finished_at
                                ? `finished ${formatDateTime(workout.finished_at, timeZone)}`
                                : null,
                            workout.bodyweight !== null
                                ? `Bodyweight ${workout.bodyweight} kg`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    />

                    <div className="flex flex-col gap-3">
                        {workout.exercises.length === 0 ? (
                            <p className="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground">
                                Add an Exercise to start logging.
                            </p>
                        ) : null}

                        {workout.exercises.map((performed, position) => (
                            <PerformedExercise
                                key={performed.id}
                                workoutId={workout.id}
                                performed={performed}
                                position={position}
                                isLast={
                                    position === workout.exercises.length - 1
                                }
                            />
                        ))}

                        <ExercisePicker
                            exercises={exercises}
                            onPick={(exercise) =>
                                router.post(
                                    WorkoutExerciseController.store.url(
                                        workout.id,
                                    ),
                                    { exercise_id: exercise.id },
                                    structureVisit,
                                )
                            }
                            description="Archived Exercises aren't offered. Restore one first to log it."
                        />
                        <InputError message={errors.exercise_id} />
                    </div>
                </div>

                <WorkoutNote key={workout.id} workout={workout} />

                {inProgress ? (
                    <div className="sticky bottom-0 -mx-4 border-t bg-background/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
                        <Form {...WorkoutController.finish.form(workout.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="h-12 w-full text-base"
                                    disabled={processing}
                                >
                                    Finish workout
                                </Button>
                            )}
                        </Form>
                    </div>
                ) : null}
            </div>
        </>
    );
}

function PerformedExercise({
    workoutId,
    performed,
    position,
    isLast,
}: {
    workoutId: number;
    performed: WorkoutExercise;
    position: number;
    isLast: boolean;
}) {
    const { exercise, sets } = performed;
    const workingSets = sets.filter((set) => !set.is_warm_up);
    const route = { workout: workoutId, exercise: performed.id };

    return (
        <ExerciseCard
            name={exercise.name}
            muscles={exercise.muscles}
            bodyweight={exercise.is_bodyweight}
            doneCount={workingSets.filter((set) => set.done).length}
            setCount={workingSets.length}
            onAddSet={() =>
                router.post(
                    WorkoutSetController.store.url(route),
                    {},
                    structureVisit,
                )
            }
            actions={
                <ItemMenu
                    label={`More actions for ${exercise.name}`}
                    removeLabel="Remove Exercise"
                    isFirst={position === 0}
                    isLast={isLast}
                    onMove={(offset) =>
                        router.put(
                            WorkoutExerciseController.move.url(route),
                            { position: position + offset },
                            structureVisit,
                        )
                    }
                    onRemove={() => {
                        if (
                            sets.some((set) => set.done) &&
                            !window.confirm(
                                `Remove ${exercise.name} and its logged Sets?`,
                            )
                        ) {
                            return;
                        }

                        router.delete(
                            WorkoutExerciseController.destroy.url(route),
                            structureVisit,
                        );
                    }}
                />
            }
        >
            {sets.map((set, setPosition) => (
                <LoggedSet
                    key={set.id}
                    workoutId={workoutId}
                    set={set}
                    index={
                        sets
                            .slice(0, setPosition + 1)
                            .filter((other) => !other.is_warm_up).length
                    }
                    bodyweight={exercise.is_bodyweight}
                    position={setPosition}
                    isLast={setPosition === sets.length - 1}
                />
            ))}
        </ExerciseCard>
    );
}

/**
 * One Set being logged. What's typed stays a draft until the Set is marked done;
 * once done, a change corrects the Actual on the server after a short pause.
 */
function LoggedSet({
    workoutId,
    set,
    index,
    bodyweight,
    position,
    isLast,
}: {
    workoutId: number;
    set: WorkoutSet;
    index: number;
    bodyweight: boolean;
    position: number;
    isLast: boolean;
}) {
    const logged: SetValues = {
        reps: set.actual_reps,
        weight: set.actual_weight,
    };
    const [draft, setDraft] = useState<SetValues>(logged);
    const [lastLogged, setLastLogged] = useState<SetValues>(logged);
    const [errors, setErrors] = useState<Errors>({});
    const correction = useRef<number | undefined>(undefined);

    // A new Actual from the server replaces the draft.
    if (
        lastLogged.reps !== logged.reps ||
        lastLogged.weight !== logged.weight
    ) {
        setLastLogged(logged);
        setDraft(logged);
    }

    useEffect(() => () => window.clearTimeout(correction.current), []);

    const target =
        set.target_reps === null
            ? undefined
            : { reps: set.target_reps, weight: set.target_weight };
    const route = { workout: workoutId, set: set.id };
    const visit = {
        ...loggingVisit,
        errorBag: `set-${set.id}`,
        onSuccess: () => setErrors({}),
        onError: (visitErrors: Errors) => setErrors(visitErrors),
    } satisfies VisitOptions;

    const markDone = () => {
        // The server fills what wasn't typed from the Target; this only mirrors it for the instant display.
        const reps = draft.reps ?? set.target_reps;
        const weight =
            draft.weight ?? set.target_weight ?? (bodyweight ? 0 : null);

        if (reps === null || weight === null) {
            setErrors({
                actual_reps:
                    reps === null ? 'Enter the reps you did.' : undefined,
                actual_weight:
                    weight === null
                        ? 'Enter the weight you lifted.'
                        : undefined,
            });

            return;
        }

        router.put(
            WorkoutSetController.markDone.url(route),
            { actual_reps: draft.reps, actual_weight: draft.weight },
            {
                ...visit,
                optimistic: (props) =>
                    withSetChanges(props, set.id, {
                        actual_reps: reps,
                        actual_weight: weight,
                        done: true,
                        meets_target: meetsTarget({ reps, weight }, target),
                    }),
            },
        );
    };

    const markNotDone = () => {
        window.clearTimeout(correction.current);

        router.delete(WorkoutSetController.markNotDone.url(route), {
            ...visit,
            optimistic: (props) =>
                withSetChanges(props, set.id, {
                    actual_reps: null,
                    actual_weight: null,
                    done: false,
                    meets_target: false,
                }),
        });
    };

    const changeActual = (actual: SetValues) => {
        setDraft(actual);
        setErrors({});

        if (!set.done) {
            return;
        }

        window.clearTimeout(correction.current);

        if (actual.reps === null || actual.weight === null) {
            return;
        }

        correction.current = window.setTimeout(
            () =>
                router.patch(
                    WorkoutSetController.update.url(route),
                    { actual_reps: actual.reps, actual_weight: actual.weight },
                    visit,
                ),
            600,
        );
    };

    const toggleWarmUp = () =>
        router.patch(
            WorkoutSetController.update.url(route),
            { is_warm_up: !set.is_warm_up },
            {
                ...visit,
                optimistic: (props) =>
                    withSetChanges(props, set.id, {
                        is_warm_up: !set.is_warm_up,
                    }),
            },
        );

    const messages = [...new Set(Object.values(errors).filter(Boolean))];

    return (
        <>
            <SetRow
                index={index}
                target={target}
                actual={draft}
                onActualChange={changeActual}
                done={set.done}
                onDoneChange={(done) => (done ? markDone() : markNotDone())}
                met={set.done ? set.meets_target : undefined}
                warmup={set.is_warm_up}
                bodyweight={bodyweight}
                invalid={{
                    reps: Boolean(errors.actual_reps),
                    weight: Boolean(errors.actual_weight),
                }}
                menu={
                    <>
                        <DropdownMenuItem onSelect={toggleWarmUp}>
                            <FlameIcon aria-hidden="true" />
                            {set.is_warm_up
                                ? 'Make a working set'
                                : 'Make a warm-up set'}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <MoveRemoveItems
                            removeLabel="Remove set"
                            isFirst={position === 0}
                            isLast={isLast}
                            onMove={(offset) =>
                                router.put(
                                    WorkoutSetController.move.url(route),
                                    { position: position + offset },
                                    structureVisit,
                                )
                            }
                            onRemove={() =>
                                router.delete(
                                    WorkoutSetController.destroy.url(route),
                                    structureVisit,
                                )
                            }
                        />
                    </>
                }
            />
            {messages.length ? (
                <div role="row">
                    <InputError
                        role="cell"
                        message={messages.join(' ')}
                        className="px-1 pb-1"
                    />
                </div>
            ) : null}
        </>
    );
}

/** The Workout Note, saved a moment after typing stops, and straight away when leaving the field. */
function WorkoutNote({ workout }: { workout: Workout }) {
    const [note, setNote] = useState(workout.note ?? '');
    const [saved, setSaved] = useState<'idle' | 'saving' | 'saved'>('idle');
    const [error, setError] = useState<string>();
    const pending = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(pending.current), []);

    const save = (value: string) => {
        window.clearTimeout(pending.current);
        pending.current = undefined;

        router.patch(
            WorkoutController.update.url(workout.id),
            { note: value },
            {
                ...loggingVisit,
                errorBag: 'note',
                onStart: () => setSaved('saving'),
                onSuccess: () => {
                    setSaved('saved');
                    setError(undefined);
                },
                onError: (errors: Errors) => {
                    setSaved('idle');
                    setError(errors.note);
                },
            },
        );
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <Label htmlFor="note">Workout Note</Label>
                <span
                    aria-live="polite"
                    className="text-xs text-muted-foreground"
                >
                    {saved === 'saving'
                        ? 'Saving…'
                        : saved === 'saved'
                          ? 'Saved'
                          : null}
                </span>
            </div>
            <textarea
                id="note"
                rows={3}
                value={note}
                placeholder="Deload, injury, bad sleep…"
                aria-invalid={error ? true : undefined}
                onChange={(event) => {
                    const value = event.target.value;

                    setNote(value);
                    window.clearTimeout(pending.current);
                    pending.current = window.setTimeout(() => save(value), 800);
                }}
                onBlur={() => {
                    if (pending.current !== undefined) {
                        save(note);
                    }
                }}
                className={cn(
                    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground md:text-sm',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                )}
            />
            <InputError message={error} />
        </div>
    );
}

ShowWorkout.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
