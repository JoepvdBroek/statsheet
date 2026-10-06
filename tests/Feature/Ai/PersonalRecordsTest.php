<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\PersonalRecords;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\AssertsDatabaseUnchanged;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class PersonalRecordsTest extends TestCase
{
    use AssertsDatabaseUnchanged, PerformsExercises, RefreshDatabase;

    public function test_it_gives_each_exercises_records_up_to_the_end_of_the_range_and_the_weeks_they_were_beaten()
    {
        $owner = User::factory()->create();
        $benchPress = Exercise::factory()->for($owner)->create(['name' => 'Bench press']);
        $squat = Exercise::factory()->for($owner)->create(['name' => 'Squat']);
        Exercise::factory()->for($owner)->create(['name' => 'Never performed']);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-08 10:00:00']), $benchPress, [$this->doneSet(5, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-22 10:00:00']), $benchPress, [
            $this->doneSet(10, '110.00')->warmUp(),
            $this->doneSet(3, '105.00'),
        ]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-24 10:00:00']), $squat, [$this->doneSet(5, '140.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-29 10:00:00']), $benchPress, [$this->doneSet(1, '120.00')]);
        $theirs = Exercise::factory()->create(['name' => 'Their press']);
        $this->perform(Workout::factory()->for($theirs->user)->finished()->create(['started_at' => '2026-09-22 10:00:00']), $theirs, [$this->doneSet(5, '50.00')]);

        $result = $this->assertDatabaseUnchangedBy(
            fn () => (new PersonalRecords($owner))->handle(new Request(['from' => '2026-09-21', 'to' => '2026-09-27'])),
        );

        $this->assertSame([
            [
                'exercise' => 'Bench press',
                'bodyweight_exercise' => false,
                'records' => [
                    'heaviest' => 105,
                    'e1rm' => 116.67,
                    'reps_at_weight' => [['weight' => 100, 'reps' => 5], ['weight' => 105, 'reps' => 3]],
                    'tonnage' => 500,
                ],
                'beaten_per_week' => ['2026-09-21' => ['heaviest']],
            ],
            [
                'exercise' => 'Squat',
                'bodyweight_exercise' => false,
                'records' => [
                    'heaviest' => 140,
                    'e1rm' => 163.33,
                    'reps_at_weight' => [['weight' => 140, 'reps' => 5]],
                    'tonnage' => 700,
                ],
                'beaten_per_week' => [],
            ],
        ], json_decode((string) $result, true));
    }
}
