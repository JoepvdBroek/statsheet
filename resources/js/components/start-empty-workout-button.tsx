import { Form } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import WorkoutController from '@/actions/App/Http/Controllers/WorkoutController';
import { Button } from '@/components/ui/button';

/** Starts a Workout without a Routine and opens it. */
export function StartEmptyWorkoutButton() {
    return (
        <Form {...WorkoutController.store.form()}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="ghost"
                    className="h-11 w-full rounded-full border border-dashed"
                    disabled={processing}
                >
                    <PlusIcon aria-hidden="true" />
                    Start an empty workout
                </Button>
            )}
        </Form>
    );
}
