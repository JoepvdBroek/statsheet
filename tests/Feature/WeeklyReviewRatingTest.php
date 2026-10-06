<?php

namespace Tests\Feature;

use App\Enums\ReviewRating;
use App\Enums\ReviewStatus;
use App\Models\User;
use App\Models\WeeklyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeeklyReviewRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_rates_a_review_with_a_thumb_and_a_comment()
    {
        $review = WeeklyReview::factory()->create();

        $response = $this->actingAs($review->user)->put(route('reviews.rating.update', $review), [
            'rating' => 'up',
            'comment' => 'Spot on about the rows.',
        ]);

        $response->assertRedirect(route('reviews.show', $review));
        $review->refresh();
        $this->assertSame(ReviewRating::Up, $review->rating);
        $this->assertSame('Spot on about the rows.', $review->rating_comment);
    }

    public function test_the_owner_changes_their_rating()
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Up, 'Spot on.')->create();

        $this->actingAs($review->user)->put(route('reviews.rating.update', $review), ['rating' => 'down'])
            ->assertRedirect(route('reviews.show', $review));

        $review->refresh();
        $this->assertSame(ReviewRating::Down, $review->rating);
        $this->assertNull($review->rating_comment);
    }

    public function test_the_owner_takes_back_their_rating()
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Down, 'Too vague.')->create();

        $this->actingAs($review->user)->delete(route('reviews.rating.destroy', $review))
            ->assertRedirect(route('reviews.show', $review));

        $review->refresh();
        $this->assertNull($review->rating);
        $this->assertNull($review->rating_comment);
    }

    public function test_the_review_page_shows_the_rating()
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Up, 'Spot on.')->create();

        $this->actingAs($review->user)->get(route('reviews.show', $review))->assertInertia(fn (Assert $page) => $page
            ->component('reviews/show')
            ->where('review.rating', 'up')
            ->where('review.rating_comment', 'Spot on.')
        );
    }

    /**
     * @return array<string, array{ReviewStatus}>
     */
    public static function statusesNotDone(): array
    {
        return [
            'pending' => [ReviewStatus::Pending],
            'failed' => [ReviewStatus::Failed],
        ];
    }

    #[DataProvider('statusesNotDone')]
    public function test_only_a_done_review_can_be_rated(ReviewStatus $status)
    {
        $review = WeeklyReview::factory()->create(['status' => $status]);

        $this->actingAs($review->user)->put(route('reviews.rating.update', $review), ['rating' => 'up'])->assertForbidden();

        $this->assertNull($review->refresh()->rating);
    }

    #[DataProvider('statusesNotDone')]
    public function test_the_rating_of_a_review_that_is_not_done_cannot_be_taken_back(ReviewStatus $status)
    {
        $review = WeeklyReview::factory()->rated(ReviewRating::Up)->create(['status' => $status]);

        $this->actingAs($review->user)->delete(route('reviews.rating.destroy', $review))->assertForbidden();

        $this->assertSame(ReviewRating::Up, $review->refresh()->rating);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidRatings(): array
    {
        return [
            'no thumb' => [['comment' => 'Nice.'], 'rating'],
            'not a thumb' => [['rating' => 'sideways'], 'rating'],
            'too long a comment' => [['rating' => 'up', 'comment' => str_repeat('a', 2001)], 'comment'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidRatings')]
    public function test_a_rating_needs_a_thumb_and_at_most_a_short_comment(array $input, string $field)
    {
        $review = WeeklyReview::factory()->create();

        $this->actingAs($review->user)->put(route('reviews.rating.update', $review), $input)->assertSessionHasErrors($field);

        $this->assertNull($review->refresh()->rating);
    }

    public function test_another_users_review_cannot_be_rated()
    {
        $theirs = WeeklyReview::factory()->rated(ReviewRating::Up, 'Mine.')->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->put(route('reviews.rating.update', $theirs), ['rating' => 'down'])->assertNotFound();
        $this->actingAs($owner)->delete(route('reviews.rating.destroy', $theirs))->assertNotFound();

        $this->assertSame(ReviewRating::Up, $theirs->refresh()->rating);
    }
}
