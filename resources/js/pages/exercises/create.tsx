import { Head } from '@inertiajs/react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import ExerciseForm from '@/components/exercise-form';
import { PageDescription } from '@/components/page-description';
import { parentScreens } from '@/lib/parent-screens';
import type { PageShell } from '@/types';

export default function CreateExercise({
    muscles,
    equipment,
}: {
    muscles: string[];
    equipment: string[];
}) {
    return (
        <>
            <Head title="New Exercise" />

            <div className="mx-auto w-full max-w-2xl p-4">
                <PageDescription>
                    A specific movement, variation included.
                </PageDescription>

                <ExerciseForm
                    muscles={muscles}
                    equipment={equipment}
                    action={ExerciseController.store()}
                    submitLabel="Create Exercise"
                />
            </div>
        </>
    );
}

CreateExercise.layout = {
    tab: 'stats',
    title: 'New Exercise',
    parent: parentScreens.exercises,
} satisfies PageShell;
