import { Head } from '@inertiajs/react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import ExerciseForm from '@/components/exercise-form';
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
                <p className="text-sm text-muted-foreground">
                    A specific movement, variation included.
                </p>

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
