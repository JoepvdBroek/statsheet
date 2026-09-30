import { Head } from '@inertiajs/react';
import { AskForReviewForm } from '@/components/ask-for-review-form';
import Heading from '@/components/heading';
import { WeeklyReviewLink } from '@/components/weekly-review-link';
import { index } from '@/routes/reviews';
import type { WeeklyReviewSummary } from '@/types';

export default function ReviewsIndex({
    currentWeek,
    reviews,
}: {
    /** This Week's Monday, as a date. */
    currentWeek: string;
    /** The owner's reviews by Week, newest first. */
    reviews: WeeklyReviewSummary[];
}) {
    return (
        <>
            <Head title="Weekly Reviews" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <Heading
                    title="Weekly Reviews"
                    description="Reread earlier reviews and their advice, or ask for the review of another Week."
                />

                <AskForReviewForm defaultDay={currentWeek} />

                {reviews.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No reviews yet. Ask for one above.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {reviews.map((review) => (
                            <li key={review.id}>
                                <WeeklyReviewLink review={review} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ReviewsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Weekly Reviews',
            href: index(),
        },
    ],
};
