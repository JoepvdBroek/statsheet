import { CheckIcon, TrophyIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

type SetValues = { reps: number | null; weight: number | null };

type SetRowProps = Omit<React.ComponentProps<'div'>, 'onChange'> & {
    /** 1-based position among the exercise's working sets. Ignored for warm-ups (shown as W). */
    index: number;
    /** Planned reps × weight (pre-filled at workout start). */
    target?: SetValues;
    /** Performed reps × weight. Controlled when given together with onActualChange. */
    actual?: SetValues;
    defaultActual?: SetValues;
    onActualChange?: (actual: SetValues) => void;
    /** A set without an Actual is not done. Controlled when given with onDoneChange. */
    done?: boolean;
    defaultDone?: boolean;
    onDoneChange?: (done: boolean) => void;
    /** Warm-up sets are logged but never count toward Volume, Goals or PRs. */
    warmup?: boolean;
    /** Bodyweight Exercise: the weight field is the added load. */
    bodyweight?: boolean;
    /** This set set a Personal Record. */
    pr?: boolean;
    /** Read-only rendering, e.g. in history or a finished workout. */
    readOnly?: boolean;
};

const fmt = (n: number | null | undefined) =>
    n === null || n === undefined ? '–' : String(n);

function meetsTarget(actual?: SetValues, target?: SetValues) {
    if (!target || target.reps === null) return true;
    if (!actual || actual.reps === null) return false;
    return (
        actual.reps >= (target.reps ?? 0) &&
        (actual.weight ?? 0) >= (target.weight ?? 0)
    );
}

function useControlled<T>(
    value: T | undefined,
    fallback: T,
    onChange?: (v: T) => void,
) {
    const [inner, setInner] = React.useState<T>(fallback);
    const controlled = value !== undefined && onChange !== undefined;
    return [
        controlled ? (value as T) : inner,
        (v: T) => {
            if (!controlled) setInner(v);
            onChange?.(v);
        },
    ] as const;
}

function SetField({
    value,
    onValue,
    label,
    placeholder,
    readOnly,
    done,
    step,
}: {
    value: number | null;
    onValue: (v: number | null) => void;
    label: string;
    placeholder: string;
    readOnly?: boolean;
    done: boolean;
    step: string;
}) {
    return (
        <input
            aria-label={label}
            inputMode="decimal"
            type="number"
            step={step}
            min={0}
            readOnly={readOnly}
            placeholder={placeholder}
            value={value ?? ''}
            onChange={(e) =>
                onValue(e.target.value === '' ? null : Number(e.target.value))
            }
            className={cn(
                'h-10 w-full min-w-0 [appearance:textfield] rounded-md border bg-transparent px-1 text-center text-base tabular-nums transition-[color,box-shadow,border-color] outline-none placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none',
                done || readOnly ? 'border-transparent' : 'border-input',
            )}
        />
    );
}

function SetRow({
    index,
    target,
    actual: actualProp,
    defaultActual,
    onActualChange,
    done: doneProp,
    defaultDone = false,
    onDoneChange,
    warmup = false,
    bodyweight = false,
    pr = false,
    readOnly = false,
    className,
    ...props
}: SetRowProps) {
    const [actual, setActual] = useControlled<SetValues>(
        actualProp,
        defaultActual ?? { reps: null, weight: null },
        onActualChange,
    );
    const [done, setDone] = useControlled<boolean>(
        doneProp,
        defaultDone,
        onDoneChange,
    );
    const met = meetsTarget(actual, target);

    const toggle = () => {
        if (readOnly) return;
        if (!done && actual.reps === null && target) {
            // One tap on an untouched set logs it as planned.
            setActual({ reps: target.reps, weight: target.weight });
        }
        setDone(!done);
    };

    return (
        <div
            role="row"
            data-slot="set-row"
            data-done={done}
            data-warmup={warmup}
            data-met={done ? met : undefined}
            className={cn(
                'grid h-12 grid-cols-[2rem_minmax(0,1fr)_4.5rem_3.75rem_2.5rem] items-center gap-2 px-1',
                warmup && 'text-muted-foreground',
                className,
            )}
            {...props}
        >
            <span
                role="cell"
                className="flex items-center justify-center gap-0.5 text-sm tabular-nums"
                aria-label={warmup ? 'Warm-up set' : `Set ${index}`}
            >
                {warmup ? 'W' : index}
                {pr ? (
                    <TrophyIcon
                        aria-label="Personal Record"
                        className="size-3 text-primary"
                    />
                ) : null}
            </span>
            <span
                role="cell"
                className="truncate text-sm text-muted-foreground tabular-nums"
            >
                {target && target.reps !== null
                    ? `${fmt(target.reps)} × ${bodyweight && !target.weight ? 'BW' : `${fmt(target.weight)}${bodyweight ? '+' : ''}`}`
                    : '–'}
            </span>
            <SetField
                label={bodyweight ? 'Added weight (kg)' : 'Weight (kg)'}
                placeholder={bodyweight ? '+0' : fmt(target?.weight)}
                value={actual.weight}
                onValue={(weight) => setActual({ ...actual, weight })}
                readOnly={readOnly}
                done={done}
                step="0.5"
            />
            <SetField
                label="Reps"
                placeholder={fmt(target?.reps)}
                value={actual.reps}
                onValue={(reps) => setActual({ ...actual, reps })}
                readOnly={readOnly}
                done={done}
                step="1"
            />
            <button
                type="button"
                role="cell"
                aria-pressed={done}
                aria-label={
                    done
                        ? met
                            ? 'Done, target met'
                            : 'Done, below target'
                        : 'Mark set done'
                }
                disabled={readOnly && !done}
                onClick={toggle}
                className={cn(
                    'flex size-10 items-center justify-center rounded-full border-[1.5px] transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 [&>svg]:size-5',
                    !done &&
                        'border-input text-muted-foreground hover:bg-accent',
                    done &&
                        warmup &&
                        'border-secondary bg-secondary text-secondary-foreground',
                    done &&
                        !warmup &&
                        met &&
                        'border-primary bg-primary text-primary-foreground',
                    done &&
                        !warmup &&
                        !met &&
                        'border-primary bg-transparent text-primary',
                    readOnly && 'pointer-events-none',
                )}
            >
                <CheckIcon aria-hidden="true" />
            </button>
        </div>
    );
}

function SetRowHeader({
    bodyweight = false,
    className,
    ...props
}: React.ComponentProps<'div'> & { bodyweight?: boolean }) {
    return (
        <div
            role="row"
            data-slot="set-row-header"
            className={cn(
                'grid grid-cols-[2rem_minmax(0,1fr)_4.5rem_3.75rem_2.5rem] items-center gap-2 px-1 text-[11px] leading-4 tracking-[0.05em] text-muted-foreground uppercase',
                className,
            )}
            {...props}
        >
            <span role="columnheader" className="text-center">
                Set
            </span>
            <span role="columnheader">Target</span>
            <span role="columnheader" className="text-center">
                {bodyweight ? '+kg' : 'kg'}
            </span>
            <span role="columnheader" className="text-center">
                Reps
            </span>
            <span role="columnheader" className="sr-only">
                Done
            </span>
        </div>
    );
}

export { SetRow, SetRowHeader, meetsTarget };
export type { SetRowProps, SetValues };
