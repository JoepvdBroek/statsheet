import { Head } from '@inertiajs/react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import ExerciseForm from '@/components/exercise-form';
import Heading from '@/components/heading';
import { create, index } from '@/routes/exercises';

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
                <Heading
                    title="New Exercise"
                    description="A specific movement, variation included."
                />

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
    breadcrumbs: [
        {
            title: 'Exercises',
            href: index(),
        },
        {
            title: 'New Exercise',
            href: create(),
        },
    ],
};
