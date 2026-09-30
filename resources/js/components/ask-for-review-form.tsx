import { Form } from '@inertiajs/react';
import WeeklyReviewController from '@/actions/App/Http/Controllers/WeeklyReviewController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Ask for the Weekly Review of the Week containing a chosen day, this Week or any past one. */
export function AskForReviewForm({
    defaultDay,
}: {
    /** The day the picker starts on, such as this Week's Monday. */
    defaultDay: string;
}) {
    return (
        <Form
            {...WeeklyReviewController.store.form()}
            className="flex flex-col gap-2"
        >
            {({ errors, processing }) => (
                <>
                    <Label htmlFor="review-week">A day in the Week</Label>
                    <div className="flex items-center gap-2">
                        <Input
                            id="review-week"
                            name="week"
                            type="date"
                            defaultValue={defaultDay}
                            aria-invalid={errors.week ? true : undefined}
                            className="w-44"
                        />
                        <Button type="submit" disabled={processing}>
                            Ask for a review
                        </Button>
                    </div>
                    <InputError message={errors.week} />
                </>
            )}
        </Form>
    );
}
