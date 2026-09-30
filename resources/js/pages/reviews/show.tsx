import { Form, Head, usePage, usePoll } from '@inertiajs/react';
import { AlertTriangleIcon, RefreshCwIcon } from 'lucide-react';
import { useEffect } from 'react';
import WeeklyReviewController from '@/actions/App/Http/Controllers/WeeklyReviewController';
import Heading from '@/components/heading';
import { MuscleTag } from '@/components/statsheet/muscle-tag';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDate, formatDateTime } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { WeeklyReview } from '@/types';

export default function ShowReview({ review }: { review: WeeklyReview }) {
    const { auth } = usePage().props;
    const pending = review.status === 'pending';
    const hasContent = review.summary !== null;
    const { start, stop } = usePoll(
        3000,
        { only: ['review'] },
        { autoStart: false, mode: 'rest' },
    );

    useEffect(() => {
        if (pending) {
            start();
        } else {
            stop();
        }
    }, [pending, start, stop]);

    return (
        <>
            <Head title={`Weekly Review, ${formatDate(review.week)}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Weekly Review"
                        description={`The Week of Monday ${formatDate(review.week)}.`}
                    />
                    {review.status === 'done' ? (
                        <AskAgain week={review.week} label="Regenerate" />
                    ) : null}
                </div>

                {pending ? (
                    <div
                        role="status"
                        className="flex items-center gap-3 rounded-xl border bg-card p-4 text-sm text-card-foreground"
                    >
                        <Spinner />
                        {hasContent
                            ? 'Writing a fresh review. The one below stays until it is in.'
                            : 'Writing your review. This takes a minute or two.'}
                    </div>
                ) : null}

                {review.status === 'failed' ? (
                    <Alert variant="destructive">
                        <AlertTriangleIcon aria-hidden="true" />
                        <AlertTitle>The review couldn't be written</AlertTitle>
                        <AlertDescription>
                            <p>
                                {hasContent
                                    ? 'The earlier review below is kept. '
                                    : ''}
                                Try again in a moment.
                            </p>
                            <AskAgain week={review.week} label="Retry" />
                        </AlertDescription>
                    </Alert>
                ) : null}

                {hasContent ? (
                    <article
                        className={
                            pending
                                ? 'flex flex-col gap-6 opacity-60'
                                : 'flex flex-col gap-6'
                        }
                    >
                        <p className="text-base leading-7">{review.summary}</p>

                        {review.muscle_notes?.length ? (
                            <section aria-labelledby="goals">
                                <h2
                                    id="goals"
                                    className="mb-3 text-base font-medium"
                                >
                                    Goals
                                </h2>
                                <ul className="flex flex-col gap-3">
                                    {review.muscle_notes.map(
                                        ({ muscle, note }) => (
                                            <li
                                                key={muscle}
                                                className="flex flex-col items-start gap-1.5 rounded-xl border bg-card p-4 text-sm text-card-foreground"
                                            >
                                                <MuscleTag muscle={muscle} />
                                                <p>{note}</p>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </section>
                        ) : null}

                        {review.advice?.length ? (
                            <section aria-labelledby="advice">
                                <h2
                                    id="advice"
                                    className="mb-3 text-base font-medium"
                                >
                                    For next Week
                                </h2>
                                <ul className="flex list-disc flex-col gap-2 pl-5 text-sm">
                                    {review.advice.map((point, index) => (
                                        <li key={index}>{point}</li>
                                    ))}
                                </ul>
                            </section>
                        ) : null}

                        {review.generated_at ? (
                            <p className="text-xs text-muted-foreground">
                                Written{' '}
                                {formatDateTime(
                                    review.generated_at,
                                    auth.user.timezone,
                                )}
                                {review.model ? ` by ${review.model}` : ''}. The
                                review only reads your log; it never changes it.
                            </p>
                        ) : null}
                    </article>
                ) : null}
            </div>
        </>
    );
}

/** Ask for a fresh review of the same Week. */
function AskAgain({ week, label }: { week: string; label: string }) {
    return (
        <Form
            {...WeeklyReviewController.store.form()}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="week" value={week} />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                    >
                        <RefreshCwIcon aria-hidden="true" />
                        {label}
                    </Button>
                </>
            )}
        </Form>
    );
}

ShowReview.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
