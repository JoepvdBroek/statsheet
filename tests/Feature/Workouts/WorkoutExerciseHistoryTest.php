<?php

namespace Tests\Feature\Workouts;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class WorkoutExerciseHistoryTest extends TestCase
{
    use PerformsExercises, RefreshDatabase;

    public function test_history_is_deferred_and_holds_each_exercises_records_and_recent_performances_from_earlier_workouts_only()
    {
        $squat = Exercise::factory()->create();
        $earlier = $this->workout($squat->user, '-1 week', finished: true);
        $this->perform($earlier, $squat, [$this->doneSet(5, '100.00')]);
        $workout = $this->workout($squat->user, '-2 days');
        $this->perform($workout, $squat, [$this->doneSet(5, '120.00')]);
        $this->perform($workout, $squat, [$this->doneSet(3, '130.00')]);
        $this->perform($this->workout($squat->user, '-1 day', finished: true), $squat, [$this->doneSet(5, '140.00')]);

        $response = $this->actingAs($squat->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('workouts/show')
            ->missing('history')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('history', 1)
                ->where("history.{$squat->id}.records", [
                    'heaviest' => 100,
                    'e1rm' => 116.67,
                    'reps_at_weight' => [['weight' => 100, 'reps' => 5]],
                    'tonnage' => 500,
                ])
                ->has("history.{$squat->id}.recent", 1)
                ->where("history.{$squat->id}.recent.0.workout_id", $earlier->id)
                ->where("history.{$squat->id}.recent.0.sets", [['reps' => 5, 'weight' => 100, 'kind' => 'working', 'top' => true]])
            )
        );
    }

    public function test_history_of_an_exercise_never_done_before_the_workout_is_empty()
    {
        $bench = Exercise::factory()->create();
        $workout = $this->workout($bench->user, '-1 day');
        $this->perform($workout, $bench, [$this->doneSet(5, '80.00')]);

        $response = $this->actingAs($bench->user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where("history.{$bench->id}", [
                    'records' => ['heaviest' => null, 'e1rm' => null, 'reps_at_weight' => [], 'tonnage' => null],
                    'recent' => [],
                ])
            )
        );
    }

    public function test_history_of_a_workout_without_exercises_is_an_empty_json_object()
    {
        $workout = Workout::factory()->create();

        $response = $this->actingAs($workout->user)->get(route('workouts.show', $workout), [
            ...$this->inertiaVisit(),
            'X-Inertia-Partial-Component' => 'workouts/show',
            'X-Inertia-Partial-Data' => 'history',
        ]);

        $this->assertStringContainsString('"history":{}', $response->getContent());
    }

    public function test_history_already_loaded_is_not_loaded_again_after_logging_a_set()
    {
        $workout = Workout::factory()->withExercises(1, 1)->create();
        $set = WorkoutSet::query()->sole();
        $loaded = $this->actingAs($workout->user)->get(route('workouts.show', $workout), $this->inertiaVisit());

        $this->put(route('workouts.sets.done', [$workout, $set]), ['actual_reps' => 5, 'actual_weight' => '100.00']);
        $response = $this->get(route('workouts.show', $workout), $this->inertiaVisitHaving($loaded));

        $this->assertSame(['exercises'], $response->json('deferredProps.default'));
        $this->assertSame($this->historyKey($loaded), $this->historyKey($response));
    }

    public function test_history_is_loaded_again_once_an_exercise_is_added()
    {
        $workout = Workout::factory()->withExercises(1, 1)->create();
        $squat = Exercise::factory()->for($workout->user)->create();
        $loaded = $this->actingAs($workout->user)->get(route('workouts.show', $workout), $this->inertiaVisit());

        $this->post(route('workouts.exercises.store', $workout), ['exercise_id' => $squat->id]);
        $response = $this->get(route('workouts.show', $workout), $this->inertiaVisitHaving($loaded));

        $this->assertSame(['exercises', 'history'], $response->json('deferredProps.default'));
        $this->assertNotSame($this->historyKey($loaded), $this->historyKey($response));
    }

    public function test_history_is_loaded_again_once_an_exercise_is_swapped()
    {
        $workout = Workout::factory()->withExercises(1, 1)->create();
        $squat = Exercise::factory()->for($workout->user)->create();
        $loaded = $this->actingAs($workout->user)->get(route('workouts.show', $workout), $this->inertiaVisit());

        $this->post(route('workouts.exercises.swap', [$workout, $workout->exercises()->sole()]), ['exercise_id' => $squat->id]);
        $response = $this->get(route('workouts.show', $workout), $this->inertiaVisitHaving($loaded));

        $this->assertSame(['exercises', 'history'], $response->json('deferredProps.default'));
        $this->assertNotSame($this->historyKey($loaded), $this->historyKey($response));
    }

    private function workout(User $owner, string $startedAgo, bool $finished = false): Workout
    {
        $factory = Workout::factory()->for($owner);

        return ($finished ? $factory->finished() : $factory)->create(['started_at' => now()->modify($startedAgo)]);
    }

    /**
     * The key the client remembers the history under, from a page the server sent.
     */
    private function historyKey(TestResponse $page): string|false
    {
        return collect($page->json('onceProps'))->search(fn (array $once) => $once['prop'] === 'history');
    }

    /**
     * Headers of an Inertia visit from a client that has loaded the deferred props of the given page.
     *
     * @return array<string, string>
     */
    private function inertiaVisitHaving(TestResponse $page): array
    {
        return [...$this->inertiaVisit(), 'X-Inertia-Except-Once-Props' => $this->historyKey($page)];
    }

    /**
     * Headers of an Inertia visit from the app's current build, as the client sends them.
     *
     * @return array<string, string>
     */
    private function inertiaVisit(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
