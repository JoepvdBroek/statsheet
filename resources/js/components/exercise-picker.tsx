import { PlusIcon, SearchIcon } from 'lucide-react';
import { useState } from 'react';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import type { Exercise } from '@/types';

type ExercisePickerProps = {
    /** The Exercises to offer; undefined while a deferred prop loads. */
    exercises?: Exercise[];
    onPick: (exercise: Exercise) => void;
    /** Why some Exercises aren't offered; shown when the search finds none. */
    description: string;
};

/** An Add Exercise button opening a searchable list of the owner's Exercises. */
export default function ExercisePicker(props: ExercisePickerProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="lg"
                onClick={() => setOpen(true)}
            >
                <PlusIcon aria-hidden="true" />
                Add Exercise
            </Button>
            <ExercisePickerDialog
                {...props}
                title="Add Exercise"
                open={open}
                onOpenChange={setOpen}
            />
        </>
    );
}

/** A searchable list of the owner's Exercises in a dialog, opened by its caller. */
export function ExercisePickerDialog({
    exercises,
    onPick,
    description,
    title,
    open,
    onOpenChange,
}: ExercisePickerProps & {
    title: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [search, setSearch] = useState('');
    const query = search.trim().toLowerCase();
    const matches = (exercises ?? []).filter((exercise) =>
        exercise.name.toLowerCase().includes(query),
    );

    return (
        <Dialog
            open={open}
            onOpenChange={(isOpen) => {
                onOpenChange(isOpen);
                setSearch('');
            }}
        >
            <DialogContent className="top-[calc(env(safe-area-inset-top)+1rem)] flex max-h-[85dvh] translate-y-0 flex-col sm:top-[50%] sm:translate-y-[-50%]">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription className="sr-only">
                    {description}
                </DialogDescription>

                <div className="relative">
                    <SearchIcon
                        aria-hidden="true"
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        type="search"
                        aria-label="Search Exercises by name"
                        placeholder="Search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="pl-9"
                    />
                </div>

                <div className="-mx-2 min-h-0 flex-1 overflow-y-auto">
                    {exercises === undefined ? (
                        <div className="flex flex-col gap-2 px-2">
                            {[0, 1, 2, 3].map((row) => (
                                <Skeleton key={row} className="h-14 w-full" />
                            ))}
                        </div>
                    ) : matches.length === 0 ? (
                        <div className="flex flex-col gap-1 px-2 py-6 text-center text-sm text-muted-foreground">
                            <p>No Exercises match.</p>
                            <p>{description}</p>
                        </div>
                    ) : (
                        <ul className="flex flex-col">
                            {matches.map((exercise) => (
                                <li key={exercise.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onPick(exercise);
                                            onOpenChange(false);
                                            setSearch('');
                                        }}
                                        className="flex w-full flex-col items-start gap-1.5 rounded-lg px-2 py-2.5 text-left outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <span className="font-medium">
                                            {exercise.name}
                                        </span>
                                        <span className="flex flex-wrap gap-1.5">
                                            {exercise.muscles.map((trained) => (
                                                <MuscleTag
                                                    key={trained.muscle}
                                                    muscle={trained.muscle}
                                                    role={trained.role}
                                                />
                                            ))}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
