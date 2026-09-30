import { useForm } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import InputError from '@/components/input-error';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { capitalize, cn } from '@/lib/utils';
import type { Exercise, MuscleRole } from '@/types';

export const nativeSelectClassName =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm';

type ExerciseFormData = {
    name: string;
    equipment: string;
    is_bodyweight: boolean;
    primary_muscles: string[];
    secondary_muscles: string[];
};

function musclesWithRole(exercise: Exercise | undefined, role: MuscleRole) {
    return (exercise?.muscles ?? [])
        .filter((trained) => trained.role === role)
        .map((trained) => trained.muscle);
}

export default function ExerciseForm({
    exercise,
    muscles,
    equipment,
    action,
    submitLabel,
}: {
    exercise?: Exercise;
    muscles: string[];
    equipment: string[];
    action: UrlMethodPair;
    submitLabel: string;
}) {
    const form = useForm<ExerciseFormData>({
        name: exercise?.name ?? '',
        equipment: exercise?.equipment ?? '',
        is_bodyweight: exercise?.is_bodyweight ?? false,
        primary_muscles: musclesWithRole(exercise, 'primary'),
        secondary_muscles: musclesWithRole(exercise, 'secondary'),
    });

    const errors = form.errors as Record<string, string | undefined>;
    const firstError = (field: string) =>
        errors[field] ??
        Object.entries(errors).find(([key]) =>
            key.startsWith(`${field}.`),
        )?.[1];

    /** A Muscle has one role at most, so choosing a role moves it out of the other. */
    const toggleMuscle = (muscle: string, role: MuscleRole) => {
        const field = `${role}_muscles` as const;
        const other =
            role === 'primary' ? 'secondary_muscles' : 'primary_muscles';

        form.setData((data) => ({
            ...data,
            [field]: data[field].includes(muscle)
                ? data[field].filter((chosen) => chosen !== muscle)
                : [...data[field], muscle],
            [other]: data[other].filter((chosen) => chosen !== muscle),
        }));
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
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
                    placeholder="e.g. Barbell bench press"
                    aria-invalid={errors.name ? true : undefined}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="equipment">Equipment</Label>
                <select
                    id="equipment"
                    className={nativeSelectClassName}
                    value={form.data.equipment}
                    onChange={(event) =>
                        form.setData('equipment', event.target.value)
                    }
                    aria-invalid={errors.equipment ? true : undefined}
                >
                    <option value="">None</option>
                    {equipment.map((value) => (
                        <option key={value} value={value}>
                            {capitalize(value)}
                        </option>
                    ))}
                </select>
                <InputError message={errors.equipment} />
            </div>

            <div className="flex items-start gap-3">
                <Checkbox
                    id="is_bodyweight"
                    checked={form.data.is_bodyweight}
                    onCheckedChange={(checked) =>
                        form.setData('is_bodyweight', checked === true)
                    }
                    className="mt-0.5"
                />
                <div className="grid gap-1">
                    <Label htmlFor="is_bodyweight">Bodyweight Exercise</Label>
                    <p className="text-sm text-muted-foreground">
                        The weight you log is the load you add on top of your
                        Bodyweight, 0 when none.
                    </p>
                </div>
            </div>

            <MusclePicker
                role="primary"
                title="Primary Muscles"
                description="At least one. Counts in full towards Volume."
                muscles={muscles}
                chosen={form.data.primary_muscles}
                onToggle={(muscle) => toggleMuscle(muscle, 'primary')}
                error={firstError('primary_muscles')}
            />

            <MusclePicker
                role="secondary"
                title="Secondary Muscles"
                description="Optional. Counts half towards Volume."
                muscles={muscles}
                chosen={form.data.secondary_muscles}
                onToggle={(muscle) => toggleMuscle(muscle, 'secondary')}
                error={firstError('secondary_muscles')}
            />

            <Button type="submit" size="lg" disabled={form.processing}>
                {submitLabel}
            </Button>
        </form>
    );
}

function MusclePicker({
    role,
    title,
    description,
    muscles,
    chosen,
    onToggle,
    error,
}: {
    role: MuscleRole;
    title: string;
    description: string;
    muscles: string[];
    chosen: string[];
    onToggle: (muscle: string) => void;
    error?: string;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="text-sm leading-none font-medium">
                {title}
            </legend>
            <p className="text-sm text-muted-foreground">{description}</p>
            <div className="mt-1 flex flex-wrap gap-2">
                {muscles.map((muscle) => {
                    const isChosen = chosen.includes(muscle);

                    return (
                        <button
                            key={muscle}
                            type="button"
                            aria-pressed={isChosen}
                            onClick={() => onToggle(muscle)}
                            className="rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <MuscleTag
                                muscle={muscle}
                                role={isChosen ? role : 'secondary'}
                                title={undefined}
                                className={cn(
                                    'h-9 px-3.5 text-sm',
                                    !isChosen && 'hover:text-foreground',
                                    isChosen &&
                                        role === 'secondary' &&
                                        'border-foreground text-foreground',
                                )}
                            />
                        </button>
                    );
                })}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
