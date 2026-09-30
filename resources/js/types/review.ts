export type ReviewStatus = 'pending' | 'done' | 'failed';

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
};
