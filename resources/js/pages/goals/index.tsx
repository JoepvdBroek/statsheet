import { Form, Head } from '@inertiajs/react';
import GoalController from '@/actions/App/Http/Controllers/GoalController';
import InputError from '@/components/input-error';
import { PageDescription } from '@/components/page-description';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parentScreens } from '@/lib/parent-screens';
import { capitalize } from '@/lib/utils';
import type { PageShell } from '@/types';

type GoalInForce = {
    muscle: string;
    /** kg per Week; empty when the Muscle has no Goal. */
    weekly_minimum: number | null;
};

export default function GoalsIndex({ goals }: { goals: GoalInForce[] }) {
    return (
        <>
            <Head title="Goals" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <PageDescription>
                    An optional weekly minimum Volume per Muscle. A change
                    applies from this Week; earlier Weeks keep the Goal they
                    had.
                </PageDescription>

                <ul className="flex flex-col gap-2">
                    {goals.map((goal) => (
                        <GoalRow key={goal.muscle} goal={goal} />
                    ))}
                </ul>
            </div>
        </>
    );
}

function GoalRow({ goal }: { goal: GoalInForce }) {
    const inputId = `goal-${goal.muscle.replace(' ', '-')}`;

    return (
        <li className="flex flex-col gap-2 rounded-xl border bg-card px-4 py-3 text-card-foreground sm:flex-row sm:items-start sm:justify-between">
            <Label htmlFor={inputId} className="pt-2.5">
                {capitalize(goal.muscle)}
            </Label>
            <div className="flex items-start gap-2">
                <Form
                    {...GoalController.update.form(goal.muscle)}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-1"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex items-center gap-2">
                                <Input
                                    id={inputId}
                                    name="weekly_minimum"
                                    type="number"
                                    inputMode="decimal"
                                    min="0.01"
                                    step="0.01"
                                    defaultValue={goal.weekly_minimum ?? ''}
                                    placeholder="No Goal"
                                    aria-invalid={
                                        errors.weekly_minimum ? true : undefined
                                    }
                                    className="w-32 tabular-nums"
                                />
                                <span className="text-sm text-muted-foreground">
                                    kg
                                </span>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Save
                                </Button>
                            </div>
                            <InputError message={errors.weekly_minimum} />
                        </>
                    )}
                </Form>
                {goal.weekly_minimum !== null ? (
                    <Form
                        {...GoalController.destroy.form(goal.muscle)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                disabled={processing}
                            >
                                Remove
                            </Button>
                        )}
                    </Form>
                ) : null}
            </div>
        </li>
    );
}

GoalsIndex.layout = {
    tab: 'stats',
    title: 'Goals',
    parent: parentScreens.stats,
} satisfies PageShell;
