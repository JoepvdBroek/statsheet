export type ReviewStatus = 'pending' | 'done' | 'failed';

/** The owner's thumbs up or down on a Weekly Review. */
export type ReviewRating = 'up' | 'down';

/** An AI-written review of one Week. Pending and failed reviews keep the content of the last successful generation, if any. */
export type WeeklyReview = {
    id: number;
    /** The reviewed Week's Monday, as a date. */
    week: string;
    status: ReviewStatus;
    summary: string | null;
    /** One note per Muscle with a Goal in force that Week. */
    muscle_notes: { muscle: string; note: string }[] | null;
    /** Advice points for the next Week. */
    advice: string[] | null;
    /** The AI model that wrote the content. */
    model: string | null;
    generated_at: string | null;
    /** The owner's rating of the content. A new generation clears it. */
    rating: ReviewRating | null;
    rating_comment: string | null;
};

/** A Weekly Review as a list shows it, without its notes and advice. */
export type WeeklyReviewSummary = Pick<
    WeeklyReview,
    'id' | 'week' | 'status' | 'summary'
>;
