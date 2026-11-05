import { Head } from '@inertiajs/react';
import {
    AskForReviewForm,
    AskForReviewFormSkeleton,
} from '@/components/ask-for-review-form';
import { PageContent, repeat, SkeletonBlock } from '@/components/page-content';
import { PageDescription } from '@/components/page-description';
import { WeeklyReviewLink } from '@/components/weekly-review-link';
import { parentScreens } from '@/lib/parent-screens';
import type { PageShell, WeeklyReviewSummary } from '@/types';

export default function ReviewsIndex({
    currentWeek,
    reviews,
}: {
    /** This Week's Monday, as a date. Every prop is undefined while an instant visit loads the page. */
    currentWeek?: string;
    /** The owner's reviews by Week, newest first. */
    reviews?: WeeklyReviewSummary[];
}) {
    return (
        <>
            <Head title="Weekly Reviews" />

            <PageContent
                loading={reviews === undefined}
                className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4"
            >
                <PageDescription>
                    Reread earlier reviews and their advice, or ask for the
                    review of another Week.
                </PageDescription>

                {currentWeek === undefined ? (
                    <AskForReviewFormSkeleton />
                ) : (
                    <AskForReviewForm defaultDay={currentWeek} />
                )}

                {reviews === undefined ? (
                    <ul className="flex flex-col gap-2">
                        {repeat(4, (index) => (
                            <li key={index}>
                                <SkeletonBlock className="h-20 rounded-lg" />
                            </li>
                        ))}
                    </ul>
                ) : reviews.length === 0 ? (
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
            </PageContent>
        </>
    );
}

ReviewsIndex.layout = {
    tab: 'stats',
    title: 'Weekly Reviews',
    parent: parentScreens.stats,
} satisfies PageShell;
