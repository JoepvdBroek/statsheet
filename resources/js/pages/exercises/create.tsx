import { Head } from '@inertiajs/react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import ExerciseForm, { ExerciseFormSkeleton } from '@/components/exercise-form';
import { PageContent } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { parentScreens } from '@/lib/parent-screens';
import type { PageShell } from '@/types';

export default function CreateExercise({
    muscles,
    equipment,
}: {
    /** Every prop is undefined while an instant visit loads the page. */
    muscles?: string[];
    equipment?: string[];
}) {
    const loading = muscles === undefined || equipment === undefined;

    return (
        <>
            <Head title="New Exercise" />

            <PageContent
                loading={loading}
                className="mx-auto w-full max-w-2xl p-4"
            >
                <PageDescription>
                    A specific movement, variation included.
                </PageDescription>

                {loading ? (
                    <ExerciseFormSkeleton submitLabel="Create Exercise" />
                ) : (
                    <ExerciseForm
                        muscles={muscles}
                        equipment={equipment}
                        action={ExerciseController.store()}
                        submitLabel="Create Exercise"
                    />
                )}
            </PageContent>
        </>
    );
}

CreateExercise.layout = {
    tab: 'stats',
    title: 'New Exercise',
    parent: parentScreens.exercises,
} satisfies PageShell;
