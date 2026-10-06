import { Link } from '@inertiajs/react';
import { formatDate } from '@/lib/utils';
import { show } from '@/routes/reviews';
import type { ReviewStatus, WeeklyReviewSummary } from '@/types';

const statusLabels: Record<ReviewStatus, string> = {
    pending: 'Being written',
    done: 'Ready',
    failed: 'Failed',
};

/** A Weekly Review as a list entry: its Week, its status and the start of its summary, opening the review. */
export function WeeklyReviewLink({ review }: { review: WeeklyReviewSummary }) {
    return (
        <Link
            href={show(review.id)}
            className="flex flex-col gap-1 rounded-lg border bg-card p-3 text-sm text-card-foreground hover:bg-accent"
        >
            <span className="flex items-center justify-between gap-2">
                <span className="font-medium">
                    Week of {formatDate(review.week)}
                </span>
                <span className="text-xs text-muted-foreground">
                    {statusLabels[review.status]}
                </span>
            </span>
            {review.summary ? (
                <span className="line-clamp-3 text-muted-foreground">
                    {review.summary}
                </span>
            ) : null}
        </Link>
    );
}
