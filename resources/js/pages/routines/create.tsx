import { Head } from '@inertiajs/react';
import RoutineController from '@/actions/App/Http/Controllers/RoutineController';
import { PageDescription } from '@/components/page-description';
import RoutineForm from '@/components/routine-form';
import { parentScreens } from '@/lib/parent-screens';
import type { Exercise, PageShell } from '@/types';

export default function CreateRoutine({
    exercises,
}: {
    exercises?: Exercise[];
}) {
    return (
        <>
            <Head title="New Routine" />

            <div className="mx-auto w-full max-w-2xl p-4">
                <PageDescription>
                    A reusable plan of Exercises, each with a Target per Set.
                </PageDescription>

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
    tab: 'routines',
    title: 'New Routine',
    parent: parentScreens.routines,
} satisfies PageShell;
