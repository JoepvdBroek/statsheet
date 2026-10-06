<?php

namespace App\Models;

use App\Enums\ReviewRating;
use App\Enums\ReviewStatus;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Database\Factories\WeeklyReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner's AI-written review of one Week: text only, never changing their data (ADR 0003). There is one per user and Week.
 * Regenerating replaces the content only once the new text is in, so a pending or failed review keeps the last good one.
 * The owner can rate the content; a new generation clears the rating, because it was about the old text.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $week The Monday of the reviewed Week
 * @property ReviewStatus $status
 * @property string|null $summary
 * @property list<array{muscle: string, note: string}>|null $muscle_notes One note per Muscle with a Goal in force that Week
 * @property list<string>|null $advice Advice points for the next Week
 * @property string|null $model The AI model that wrote the content
 * @property CarbonImmutable|null $generated_at When the content was written
 * @property ReviewRating|null $rating The owner's thumbs up or down on the content
 * @property string|null $rating_comment The owner's comment with their rating
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['week', 'status'])]
class WeeklyReview extends Model
{
    /** @use HasFactory<WeeklyReviewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week' => 'date',
            'status' => ReviewStatus::class,
            'muscle_notes' => 'array',
            'advice' => 'array',
            'generated_at' => 'datetime',
            'rating' => ReviewRating::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The reviewed Week, as WeekCalendar gives it: its Monday at midnight in the owner's timezone.
     */
    public function reviewedWeek(): CarbonImmutable
    {
        return WeekCalendar::for($this->user)->weekOfDay($this->week->toDateString());
    }

    /**
     * Replace the content with a new generation and mark the review done. The rating was about the old text, so it is cleared.
     *
     * @param  array{summary: string, muscle_notes: list<array{muscle: string, note: string}>, advice: list<string>}  $parts
     */
    public function storeGeneration(array $parts, ?string $model): void
    {
        $this->forceFill([
            'status' => ReviewStatus::Done,
            'summary' => $parts['summary'],
            'muscle_notes' => $parts['muscle_notes'],
            'advice' => $parts['advice'],
            'model' => $model,
            'generated_at' => now(),
            'rating' => null,
            'rating_comment' => null,
        ])->save();
    }

    /**
     * Rate the content with a thumbs up or down and an optional comment, replacing any earlier rating.
     */
    public function rate(ReviewRating $rating, ?string $comment): void
    {
        $this->forceFill(['rating' => $rating, 'rating_comment' => $comment])->save();
    }

    /**
     * Take back the owner's rating.
     */
    public function clearRating(): void
    {
        $this->forceFill(['rating' => null, 'rating_comment' => null])->save();
    }

    /**
     * Mark the latest generation failed, keeping the content of the last successful one.
     */
    public function markFailed(): void
    {
        $this->forceFill(['status' => ReviewStatus::Failed])->save();
    }
}
