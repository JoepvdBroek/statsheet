import { Head } from '@inertiajs/react';
import RoutineController from '@/actions/App/Http/Controllers/RoutineController';
import Heading from '@/components/heading';
import RoutineForm from '@/components/routine-form';
import { create, index } from '@/routes/routines';
import type { Exercise } from '@/types';

export default function CreateRoutine({
    exercises,
}: {
    exercises?: Exercise[];
}) {
    return (
        <>
            <Head title="New Routine" />

            <div className="mx-auto w-full max-w-2xl p-4">
                <Heading
                    title="New Routine"
                    description="A reusable plan of Exercises, each with a Target per Set."
                />

                <RoutineForm
                    exercises={exercises}
                    action={RoutineController.store()}
                    submitLabel="Create Routine"
                />
            </div>
        </>
    );
}

CreateRoutine.layout = {
    breadcrumbs: [
        {
            title: 'Routines',
            href: index(),
        },
        {
            title: 'New Routine',
            href: create(),
        },
    ],
};
