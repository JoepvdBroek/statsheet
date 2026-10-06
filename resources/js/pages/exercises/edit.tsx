import { Form, Head } from '@inertiajs/react';
import ExerciseController from '@/actions/App/Http/Controllers/ExerciseController';
import ExerciseForm from '@/components/exercise-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { parentScreens } from '@/lib/parent-screens';
import type { Exercise, PageShell } from '@/types';

export default function EditExercise({
    exercise,
    muscles,
    equipment,
}: {
    exercise: Exercise;
    muscles: string[];
    equipment: string[];
}) {
    return (
        <>
            <Head title={`Edit ${exercise.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-10 p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {exercise.name}
                    </p>

                    <ExerciseForm
                        exercise={exercise}
                        muscles={muscles}
                        equipment={equipment}
                        action={ExerciseController.update(exercise.id)}
                        submitLabel="Save"
                    />
                </div>

                <div className="space-y-4 border-t pt-6">
                    <Heading
                        variant="small"
                        title="Delete Exercise"
                        description="An Exercise a Routine or Workout uses is archived instead, so your history stays intact. You can restore it later."
                    />

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">Delete</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Delete {exercise.name}?</DialogTitle>
                            <DialogDescription>
                                If a Routine or Workout uses it, it is archived
                                instead: hidden from pickers, but restorable.
                            </DialogDescription>

                            <Form
                                {...ExerciseController.destroy.form(
                                    exercise.id,
                                )}
                            >
                                {({ processing }) => (
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            Delete
                                        </Button>
                                    </DialogFooter>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </>
    );
}

EditExercise.layout = {
    tab: 'stats',
    title: 'Edit Exercise',
    parent: parentScreens.exercises,
} satisfies PageShell;
