<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\WorkoutLog;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\AssertsDatabaseUnchanged;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WorkoutLogTest extends TestCase
{
    use AssertsDatabaseUnchanged, PerformsExercises, RefreshDatabase;

    public function test_it_gives_the_owners_workouts_with_their_sets_and_notes_per_week()
    {
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $pushDay = Routine::factory()->for($owner)->create(['name' => 'Push Day']);
        $benchPress = Exercise::factory()->for($owner)->create(['name' => 'Bench press']);
        $dip = Exercise::factory()->for($owner)->bodyweight()->create(['name' => 'Dip']);
        $sundayNight = Workout::factory()->for($owner)->for($pushDay)->finished()->create([
            'started_at' => '2026-09-21 03:30:00',
            'bodyweight' => '80.00',
            'note' => 'Bad sleep.',
        ]);
        $this->perform($sundayNight, $benchPress, [
            $this->doneSet(10, '60.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            WorkoutSet::factory()->state(['target_reps' => 5, 'target_weight' => '100.00'])->notDone(),
        ]);
        $this->perform($sundayNight, $dip, [$this->doneSet(8, '10.00')]);
        $inProgress = Workout::factory()->for($owner)->create(['started_at' => '2026-09-29 12:00:00']);
        $this->perform($inProgress, $benchPress, [WorkoutSet::factory()->state(['target_reps' => null, 'target_weight' => null])->state(['actual_reps' => 3, 'actual_weight' => '105.00'])]);
        $theirs = Workout::factory()->finished()->create(['started_at' => '2026-09-16 12:00:00', 'note' => 'Not yours.']);
        $this->perform($theirs, Exercise::factory()->for($theirs->user)->create(), [$this->doneSet(5, '50.00')]);

        $result = $this->assertDatabaseUnchangedBy(
            fn () => (new WorkoutLog($owner))->handle(new Request(['from' => '2026-09-14', 'to' => '2026-09-28'])),
        );

        $this->assertSame([
            '2026-09-14' => [[
                'started_at' => '2026-09-20 23:30',
                'in_progress' => false,
                'routine' => 'Push Day',
                'bodyweight' => 80,
                'note' => 'Bad sleep.',
                'exercises' => [
                    ['exercise' => 'Bench press', 'bodyweight_exercise' => false, 'sets' => [
                        ['target_reps' => 10, 'target_weight' => 60, 'actual_reps' => 10, 'actual_weight' => 60, 'warm_up' => true],
                        ['target_reps' => 5, 'target_weight' => 100, 'actual_reps' => 5, 'actual_weight' => 100, 'warm_up' => false],
                        ['target_reps' => 5, 'target_weight' => 100, 'actual_reps' => null, 'actual_weight' => null, 'warm_up' => false],
                    ]],
                    ['exercise' => 'Dip', 'bodyweight_exercise' => true, 'sets' => [
                        ['target_reps' => 8, 'target_weight' => 10, 'actual_reps' => 8, 'actual_weight' => 10, 'warm_up' => false],
                    ]],
                ],
            ]],
            '2026-09-21' => [],
            '2026-09-28' => [[
                'started_at' => '2026-09-29 08:00',
                'in_progress' => true,
                'routine' => null,
                'bodyweight' => null,
                'note' => null,
                'exercises' => [
                    ['exercise' => 'Bench press', 'bodyweight_exercise' => false, 'sets' => [
                        ['target_reps' => null, 'target_weight' => null, 'actual_reps' => 3, 'actual_weight' => 105, 'warm_up' => false],
                    ]],
                ],
            ]],
        ], json_decode((string) $result, true));
    }

    public function test_it_reads_at_most_twelve_weeks_at_a_time()
    {
        $this->expectException(ValidationException::class);

        (new WorkoutLog(User::factory()->create()))->handle(new Request(['from' => '2026-06-01', 'to' => '2026-09-28']));
    }
}
