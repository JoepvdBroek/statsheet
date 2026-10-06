import { Head } from '@inertiajs/react';
import { AskForReviewForm } from '@/components/ask-for-review-form';
import { WeeklyReviewLink } from '@/components/weekly-review-link';
import { parentScreens } from '@/lib/parent-screens';
import type { PageShell, WeeklyReviewSummary } from '@/types';

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
                <p className="text-sm text-muted-foreground">
                    Reread earlier reviews and their advice, or ask for the
                    review of another Week.
                </p>

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
    tab: 'stats',
    title: 'Weekly Reviews',
    parent: parentScreens.stats,
} satisfies PageShell;
