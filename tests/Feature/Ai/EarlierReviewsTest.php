<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\EarlierReviews;
use App\Models\User;
use App\Models\WeeklyReview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\AssertsDatabaseUnchanged;
use Tests\TestCase;

class EarlierReviewsTest extends TestCase
{
    use AssertsDatabaseUnchanged, RefreshDatabase;

    public function test_it_gives_the_owners_reviews_of_weeks_in_the_range_before_the_reviewed_week()
    {
        $owner = User::factory()->create();
        WeeklyReview::factory()->for($owner)->create(['week' => '2026-08-31']);
        WeeklyReview::factory()->for($owner)->create([
            'week' => '2026-09-07',
            'summary' => 'Chest fell short.',
            'muscle_notes' => [['muscle' => 'chest', 'note' => 'Missed by 500 kg.']],
            'advice' => ['Add a fourth Set of bench.'],
            'generated_at' => '2026-09-10 18:00:00',
        ]);
        WeeklyReview::factory()->for($owner)->pending()->create(['week' => '2026-09-14']);
        WeeklyReview::factory()->for($owner)->failed()->create(['week' => '2026-09-21', 'summary' => 'Kept from before.', 'muscle_notes' => [], 'advice' => ['Rest.'], 'generated_at' => '2026-09-28 04:00:00']);
        WeeklyReview::factory()->for($owner)->create(['week' => '2026-09-28']);
        WeeklyReview::factory()->create(['week' => '2026-09-14', 'summary' => 'Not yours.']);
        $reviewedWeek = CarbonImmutable::parse('2026-09-28', $owner->timezone);

        $result = $this->assertDatabaseUnchangedBy(
            fn () => (new EarlierReviews($owner, $reviewedWeek))->handle(new Request(['from' => '2026-09-07', 'to' => '2026-10-04'])),
        );

        $this->assertSame([
            '2026-09-07' => [
                'summary' => 'Chest fell short.',
                'muscle_notes' => [['muscle' => 'chest', 'note' => 'Missed by 500 kg.']],
                'advice' => ['Add a fourth Set of bench.'],
                'written_at' => '2026-09-10 20:00',
            ],
            '2026-09-21' => [
                'summary' => 'Kept from before.',
                'muscle_notes' => [],
                'advice' => ['Rest.'],
                'written_at' => '2026-09-28 06:00',
            ],
        ], json_decode((string) $result, true));
    }
}
