import { Form } from '@inertiajs/react';
import { ThumbsDownIcon, ThumbsUpIcon } from 'lucide-react';
import WeeklyReviewController from '@/actions/App/Http/Controllers/WeeklyReviewController';
import InputError from '@/components/input-error';
import { Button, buttonVariants } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { ReviewRating, WeeklyReview } from '@/types';

const thumbs: {
    value: ReviewRating;
    label: string;
    Icon: typeof ThumbsUpIcon;
}[] = [
    { value: 'up', label: 'Useful', Icon: ThumbsUpIcon },
    { value: 'down', label: 'Not useful', Icon: ThumbsDownIcon },
];

/** Rate a written Weekly Review with a thumbs up or down and an optional comment, change the rating, or take it back. */
export function ReviewRatingForm({ review }: { review: WeeklyReview }) {
    return (
        <section
            aria-labelledby="rating"
            className="flex flex-col gap-3 rounded-xl border bg-card p-4 text-sm text-card-foreground"
        >
            <h2 id="rating" className="text-base font-medium">
                Was this review useful?
            </h2>

            <Form
                {...WeeklyReviewController.rate.form(review.id)}
                options={{ preserveScroll: true }}
                className="flex flex-col gap-3"
            >
                {({ errors, processing }) => (
                    <>
                        <fieldset className="flex gap-2">
                            <legend className="sr-only">Rating</legend>
                            {thumbs.map(({ value, label, Icon }) => (
                                <label key={value} className="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="rating"
                                        value={value}
                                        defaultChecked={review.rating === value}
                                        className="peer sr-only"
                                    />
                                    <span
                                        className={cn(
                                            buttonVariants({
                                                variant: 'outline',
                                                size: 'sm',
                                            }),
                                            'peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground',
                                            'peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50',
                                        )}
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        {label}
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={errors.rating} />

                        <div className="grid gap-2">
                            <Label htmlFor="rating-comment">
                                Comment (optional)
                            </Label>
                            <Textarea
                                id="rating-comment"
                                name="comment"
                                rows={2}
                                defaultValue={review.rating_comment ?? ''}
                                placeholder="What made it useful, or not?"
                                aria-invalid={errors.comment ? true : undefined}
                            />
                            <InputError message={errors.comment} />
                        </div>

                        <div>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                {review.rating ? 'Change rating' : 'Rate'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {review.rating ? (
                <Form
                    {...WeeklyReviewController.clearRating.form(review.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="link"
                            size="sm"
                            disabled={processing}
                            className="h-auto px-0 text-muted-foreground"
                        >
                            Take back my rating
                        </Button>
                    )}
                </Form>
            ) : null}
        </section>
    );
}
