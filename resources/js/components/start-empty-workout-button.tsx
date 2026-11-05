import { Form } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { LockedButton } from '@/components/locked-button';
import { nameInstantAction } from '@/lib/failed-visits';

/** Starts a Workout without a Routine and opens it at once, as an instant visit. */
export function StartEmptyWorkoutButton() {
    return (
        <Form
            {...WorkoutController.store.form()}
            component="workouts/show"
            onBefore={(visit) => nameInstantAction(visit, 'start the workout')}
        >
            {({ processing }) => (
                <LockedButton
                    type="submit"
                    variant="ghost"
                    className="h-11 w-full rounded-full border border-dashed"
                    locked={processing}
                    icon={<PlusIcon aria-hidden="true" />}
                >
                    Start an empty workout
                </LockedButton>
            )}
        </Form>
    );
}
