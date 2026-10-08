<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class ExerciseProgressTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_a_drop_set_counts_toward_personal_records()
    {
        $squat = Exercise::factory()->create();
        $this->perform($this->workout($squat->user, '-1 week'), $squat, [
            $this->doneSet(5, '100.00'),
            $this->doneSet(12, '80.00')->drop(),
        ]);

        $response = $this->actingAs($squat->user)->get(route('exercises.show', $squat));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('records.tonnage', 960)
            ->where('records.reps_at_weight', [['weight' => 80, 'reps' => 12], ['weight' => 100, 'reps' => 5]])
            ->etc()
        );
    }

    public function test_the_heaviest_weight_and_estimated_1rm_leave_out_warm_up_and_not_done_sets()
    {
        $squat = Exercise::factory()->create();
        $this->perform($this->workout($squat->user, '-2 weeks'), $squat, [
            $this->doneSet(5, '140.00'),
            $this->doneSet(3, '160.00')->warmUp(),
        ]);
        $this->perform($this->workout($squat->user, '-1 week'), $squat, [
            $this->doneSet(5, '145.00'),
            WorkoutSet::factory()->state(['target_reps' => 1, 'target_weight' => '170.00'])->notDone(),
        ]);

        $response = $this->actingAs($squat->user)->get(route('exercises.show', $squat));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('exercises/show')
            ->where('exercise.id', $squat->id)
            ->where('records.heaviest', 145)
            ->where('records.e1rm', 169.17)
            ->etc()
        );
    }

    public function test_the_best_estimated_1rm_uses_epley_and_skips_sets_over_12_reps()
    {
        $bench = Exercise::factory()->create();
        $this->perform($this->workout($bench->user, '-1 week'), $bench, [
            $this->doneSet(5, '100.00'),
            $this->doneSet(12, '80.00'),
            $this->doneSet(13, '100.00'),
        ]);

        $response = $this->actingAs($bench->user)->get(route('exercises.show', $bench));

        $response->assertInertia(fn (Assert $page) => $page->where('records.e1rm', 116.67)->etc());
    }

    public function test_the_estimated_1rm_is_empty_when_every_set_has_over_12_reps()
    {
        $bench = Exercise::factory()->create();
        $this->perform($this->workout($bench->user, '-1 week'), $bench, [$this->doneSet(15, '60.00')]);

        $response = $this->actingAs($bench->user)->get(route('exercises.show', $bench));

        $response->assertInertia(fn (Assert $page) => $page->where('records.heaviest', 60)->where('records.e1rm', null)->etc());
    }

    public function test_the_best_set_tonnage_is_the_highest_reps_times_weight_of_one_set()
    {
        $row = Exercise::factory()->create();
        $this->perform($this->workout($row->user, '-1 week'), $row, [
            $this->doneSet(20, '40.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            $this->doneSet(10, '60.00'),
        ]);

        $response = $this->actingAs($row->user)->get(route('exercises.show', $row));

        $response->assertInertia(fn (Assert $page) => $page->where('records.tonnage', 600)->etc());
    }

    public function test_most_reps_at_a_weight_is_the_best_reps_for_each_weight_lightest_first()
    {
        $deadlift = Exercise::factory()->create();
        $this->perform($this->workout($deadlift->user, '-2 weeks'), $deadlift, [
            $this->doneSet(12, '60.00')->warmUp(),
            $this->doneSet(5, '100.00'),
            $this->doneSet(10, '60.00'),
        ]);
        $this->perform($this->workout($deadlift->user, '-1 week'), $deadlift, [
            $this->doneSet(8, '100.00'),
            $this->doneSet(3, '100.00'),
            $this->doneSet(6, '102.50'),
        ]);

        $response = $this->actingAs($deadlift->user)->get(route('exercises.show', $deadlift));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('records.reps_at_weight', [
                ['weight' => 60, 'reps' => 10],
                ['weight' => 100, 'reps' => 8],
                ['weight' => 102.5, 'reps' => 6],
            ])
            ->etc()
        );
    }

    public function test_an_exercise_without_done_sets_has_no_records()
    {
        $squat = Exercise::factory()->create();
        $this->perform($this->workout($squat->user, '-1 week'), $squat, [
            $this->doneSet(5, '60.00')->warmUp(),
            WorkoutSet::factory()->notDone(),
        ]);

        $response = $this->actingAs($squat->user)->get(route('exercises.show', $squat));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('records', ['heaviest' => null, 'e1rm' => null, 'reps_at_weight' => [], 'tonnage' => null])
            ->etc()
        );
    }

    public function test_a_bodyweight_exercise_uses_the_added_load_for_heaviest_and_reps_and_adds_bodyweight_for_estimated_1rm_and_tonnage()
    {
        $pullUp = Exercise::factory()->bodyweight()->create();
        $pullUp->user->update(['bodyweight' => '95.00']);
        $this->perform($this->workout($pullUp->user, '-1 week', ['bodyweight' => '80.00']), $pullUp, [
            $this->doneSet(10, '0.00'),
            $this->doneSet(5, '10.00'),
        ]);

        $response = $this->actingAs($pullUp->user)->get(route('exercises.show', $pullUp));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('records', [
                'heaviest' => 10,
                'e1rm' => 106.67,
                'reps_at_weight' => [['weight' => 0, 'reps' => 10], ['weight' => 10, 'reps' => 5]],
                'tonnage' => 800,
            ])
            ->etc()
        );
    }

    public function test_a_bodyweight_exercise_counts_only_its_added_load_when_the_workout_has_no_bodyweight()
    {
        $dip = Exercise::factory()->bodyweight()->create();
        $this->perform($this->workout($dip->user, '-1 week', ['bodyweight' => null]), $dip, [$this->doneSet(6, '20.00')]);

        $response = $this->actingAs($dip->user)->get(route('exercises.show', $dip));

        $response->assertInertia(fn (Assert $page) => $page->where('records.e1rm', 24)->where('records.tonnage', 120)->etc());
    }

    public function test_progress_holds_the_best_estimated_1rm_and_heaviest_weight_per_workout_oldest_first()
    {
        $this->freezeSecond();
        $squat = Exercise::factory()->create();
        $owner = $squat->user;
        $second = $this->workout($owner, '-1 week');
        $this->perform($second, $squat, [$this->doneSet(8, '100.00')]);
        $this->perform($second, Exercise::factory()->for($owner)->create(), [$this->doneSet(1, '200.00')]);
        $this->perform($second, $squat, [$this->doneSet(1, '120.00')]);
        $first = $this->workout($owner, '-2 weeks');
        $this->perform($first, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(3, '110.00')]);
        $this->perform($this->workout($owner, '-3 days'), $squat, [$this->doneSet(5, '130.00')->warmUp()]);
        $inProgress = Workout::factory()->for($owner)->create(['started_at' => now()->subHour()]);
        $this->perform($inProgress, $squat, [$this->doneSet(15, '100.00')]);

        $response = $this->actingAs($owner)->get(route('exercises.show', $squat));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('progress', [
                ['workout_id' => $first->id, 'started_at' => $first->started_at->toIso8601String(), 'e1rm' => 121, 'heaviest' => 110],
                ['workout_id' => $second->id, 'started_at' => $second->started_at->toIso8601String(), 'e1rm' => 126.67, 'heaviest' => 120],
                ['workout_id' => $inProgress->id, 'started_at' => $inProgress->started_at->toIso8601String(), 'e1rm' => null, 'heaviest' => 100],
            ])
            ->etc()
        );
    }

    public function test_recent_performances_list_each_workouts_done_sets_newest_first_with_its_top_set_and_the_records_it_beat()
    {
        $this->freezeSecond();
        $pullUp = Exercise::factory()->bodyweight()->create();
        $owner = $pullUp->user;
        $routine = Routine::factory()->for($owner)->create(['name' => 'Pull day']);
        $older = $this->workout($owner, '-2 weeks', ['bodyweight' => '78.00']);
        $this->perform($older, $pullUp, [$this->doneSet(8, '0.00'), $this->doneSet(13, '0.00')]);
        $newer = $this->workout($owner, '-1 week', ['routine_id' => $routine->id, 'bodyweight' => '80.00']);
        $this->perform($newer, $pullUp, [
            $this->doneSet(5, '0.00')->warmUp(),
            $this->doneSet(12, '0.00'),
            WorkoutSet::factory()->notDone(),
        ]);
        $this->perform($newer, $pullUp, [$this->doneSet(6, '20.00')]);
        $this->perform($this->workout($owner, '-3 days'), Exercise::factory()->for($owner)->create(), [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($owner)->get(route('exercises.show', $pullUp));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('recent', [
                [
                    'workout_id' => $newer->id,
                    'started_at' => $newer->started_at->toIso8601String(),
                    'routine' => 'Pull day',
                    'e1rm' => 120,
                    'intensity' => 101,
                    'average_weight' => 86.67,
                    'new_records' => ['heaviest', 'e1rm'],
                    'sets' => [
                        ['reps' => 5, 'weight' => 0, 'kind' => 'warm_up', 'top' => false],
                        ['reps' => 12, 'weight' => 0, 'kind' => 'working', 'top' => false],
                        ['reps' => 6, 'weight' => 20, 'kind' => 'working', 'top' => true],
                    ],
                ],
                [
                    'workout_id' => $older->id,
                    'started_at' => $older->started_at->toIso8601String(),
                    'routine' => null,
                    'e1rm' => 98.8,
                    'intensity' => null,
                    'average_weight' => 78,
                    'new_records' => ['reps', 'tonnage'],
                    'sets' => [
                        ['reps' => 8, 'weight' => 0, 'kind' => 'working', 'top' => true],
                        ['reps' => 13, 'weight' => 0, 'kind' => 'working', 'top' => false],
                    ],
                ],
            ])
            ->etc()
        );
    }

    #[DataProvider('ageOfTheEarlierBest')]
    public function test_intensity_drops_the_earlier_estimated_1rm_by_1_percent_per_week_after_3_weeks_by_at_most_15_percent(string $earlierBestAgo, int $intensity)
    {
        $this->freezeSecond();
        $bench = Exercise::factory()->create();
        $this->perform($this->workout($bench->user, $earlierBestAgo), $bench, [$this->doneSet(6, '100.00')]);
        $this->perform($this->workout($bench->user, '-1 day'), $bench, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($bench->user)->get(route('exercises.show', $bench));

        $response->assertInertia(fn (Assert $page) => $page->where('recent.0.intensity', $intensity)->etc());
    }

    /**
     * When the earlier Set with an Estimated 1RM of 120 kg was done, and the Intensity of a 100 kg Set a day ago.
     *
     * @return array<string, array{string, int}>
     */
    public static function ageOfTheEarlierBest(): array
    {
        return [
            'within the 3 weeks of grace' => ['-22 days', 83],
            '7 weeks past the grace' => ['-71 days', 90],
            'at most 15% lower' => ['-31 weeks', 98],
        ];
    }

    public function test_a_recent_estimated_1rm_sets_the_intensity_once_an_older_higher_one_has_decayed_below_it()
    {
        $this->freezeSecond();
        $bench = Exercise::factory()->create();
        $this->perform($this->workout($bench->user, '-30 weeks'), $bench, [$this->doneSet(1, '140.00')]);
        $this->perform($this->workout($bench->user, '-2 weeks'), $bench, [$this->doneSet(1, '125.00')]);
        $this->perform($this->workout($bench->user, '-1 day'), $bench, [$this->doneSet(5, '100.00')]);

        $response = $this->actingAs($bench->user)->get(route('exercises.show', $bench));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('recent.0.intensity', 77)
            ->where('recent.1.intensity', 102)
            ->etc()
        );
    }

    public function test_recent_performances_are_the_last_ten_workouts()
    {
        $squat = Exercise::factory()->create();
        foreach (range(1, 11) as $weeksAgo) {
            $this->perform($this->workout($squat->user, "-{$weeksAgo} weeks"), $squat, [$this->doneSet(5, '100.00')]);
        }

        $response = $this->actingAs($squat->user)->get(route('exercises.show', $squat));

        $response->assertInertia(fn (Assert $page) => $page->has('recent', 10)->has('progress', 11)->etc());
    }

    public function test_correcting_or_deleting_a_past_set_updates_the_records()
    {
        $squat = Exercise::factory()->create();
        $workout = $this->workout($squat->user, '-1 week');
        $this->perform($workout, $squat, [$this->doneSet(5, '100.00'), $this->doneSet(3, '120.00')]);
        [$first, $heaviest] = $workout->sets()->orderBy('position')->get()->all();

        $this->actingAs($squat->user)
            ->patch(route('workouts.sets.update', [$workout, $first]), ['actual_reps' => 6, 'actual_weight' => 110])
            ->assertSessionHasNoErrors();
        $this->delete(route('workouts.sets.destroy', [$workout, $heaviest]));

        $this->get(route('exercises.show', $squat))->assertInertia(fn (Assert $page) => $page
            ->where('records', [
                'heaviest' => 110,
                'e1rm' => 132,
                'reps_at_weight' => [['weight' => 110, 'reps' => 6]],
                'tonnage' => 660,
            ])
            ->etc()
        );
    }

    /**
     * A finished Workout of the owner that started the given time ago.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function workout(User $owner, string $startedAgo, array $attributes = []): Workout
    {
        return Workout::factory()->for($owner)->finished()->create(['started_at' => now()->modify($startedAgo), ...$attributes]);
    }
}
