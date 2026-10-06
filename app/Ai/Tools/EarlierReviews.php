<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's earlier Weekly Reviews in a range: those of Weeks before the one being reviewed, so the agent can check whether their advice was followed. It only reads.
 */
class EarlierReviews extends WeekRangeTool
{
    /**
     * @param  CarbonImmutable  $reviewedWeek  The Week being reviewed, as WeekCalendar gives it
     */
    public function __construct(User $user, private CarbonImmutable $reviewedWeek)
    {
        parent::__construct($user);
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'The earlier Weekly Reviews of Weeks in a range of at most '.static::MAX_WEEKS.' Weeks, before the Week being reviewed, keyed by the Week\'s Monday, oldest first: '
            .'each with its summary, its note per Muscle with a Goal, its advice for the next Week, and when it was written in the owner\'s timezone '
            .'(a review written before its Week ended saw only part of it). Weeks without a review are left out.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        ['start' => $start, 'end' => $end] = $this->range($request);

        $reviews = $this->user->weeklyReviews()
            ->whereDate('week', '>=', $start->toDateString())
            ->whereDate('week', '<', $end->min($this->reviewedWeek)->toDateString())
            ->whereNotNull('summary')
            ->orderBy('week')
            ->get()
            ->mapWithKeys(fn (WeeklyReview $review) => [$review->week->toDateString() => [
                'summary' => $review->summary,
                'muscle_notes' => $review->muscle_notes,
                'advice' => $review->advice,
                'written_at' => $review->generated_at?->setTimezone($this->user->timezone)->format('Y-m-d H:i'),
            ]]);

        return json_encode((object) $reviews->all(), JSON_THROW_ON_ERROR);
    }
}
