import { Form, Head } from '@inertiajs/react';
import RoutineController from '@/actions/App/Http/Controllers/RoutineController';
import Heading from '@/components/heading';
import RoutineForm from '@/components/routine-form';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/routines';
import type { Exercise, Routine } from '@/types';

export default function EditRoutine({
    routine,
    exercises,
}: {
    routine: Routine;
    exercises?: Exercise[];
}) {
    return (
        <>
            <Head title={`Edit ${routine.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-10 p-4">
                <div>
                    <Heading title="Edit Routine" description={routine.name} />

                    <RoutineForm
                        routine={routine}
                        exercises={exercises}
                        action={RoutineController.update(routine.id)}
                        submitLabel="Save"
                    />
                </div>

                <div className="space-y-4 border-t pt-6">
                    {routine.archived ? (
                        <>
                            <Heading
                                variant="small"
                                title="Restore Routine"
                                description="This Routine is archived. Restoring it brings it back to your list."
                            />
                            <Form
                                {...RoutineController.restore.form(routine.id)}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Restore
                                    </Button>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading
                                variant="small"
                                title="Archive Routine"
                                description="Hides it from your list. Workouts started from it keep their link, and you can restore it any time."
                            />
                            <Form
                                {...RoutineController.archive.form(routine.id)}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Archive
                                    </Button>
                                )}
                            </Form>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

EditRoutine.layout = {
    breadcrumbs: [
        {
            title: 'Routines',
            href: index(),
        },
    ],
};
