<?php

namespace Tests\Feature\Routines;

use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutineManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_a_routine_with_ordered_exercises_and_sets()
    {
        $owner = User::factory()->create();
        $bench = Exercise::factory()->for($owner)->create(['name' => 'Bench press']);
        $dip = Exercise::factory()->for($owner)->bodyweight()->create(['name' => 'Dip']);

        $response = $this
            ->actingAs($owner)
            ->post(route('routines.store'), [
                'name' => 'Push day',
                'exercises' => [
                    ['exercise_id' => $dip->id, 'sets' => [
                        ['target_reps' => 10, 'target_weight' => 0, 'is_warm_up' => false],
                    ]],
                    ['exercise_id' => $bench->id, 'sets' => [
                        ['target_reps' => 10, 'target_weight' => 40, 'is_warm_up' => true],
                        ['target_reps' => 8, 'target_weight' => 82.5, 'is_warm_up' => false],
                    ]],
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'))
            ->assertInertiaFlash('toast.message', 'Routine created.');

        $routine = $owner->routines()->sole();

        $this->assertSame('Push day', $routine->name);
        $this->assertNull($routine->archived_at);
        $this->assertSame([
            ['exercise' => 'Dip', 'sets' => [[10, '0.00', false]]],
            ['exercise' => 'Bench press', 'sets' => [[10, '40.00', true], [8, '82.50', false]]],
        ], $this->plan($routine->refresh()));
    }

    public function test_target_weight_may_be_zero_with_up_to_two_decimals()
    {
        $exercise = Exercise::factory()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->post(route('routines.store'), [
                'name' => 'Pull day',
                'exercises' => [
                    ['exercise_id' => $exercise->id, 'sets' => [
                        ['target_reps' => 1, 'target_weight' => 0, 'is_warm_up' => false],
                        ['target_reps' => 5, 'target_weight' => '101.25', 'is_warm_up' => false],
                    ]],
                ],
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(
            [['exercise' => $exercise->name, 'sets' => [[1, '0.00', false], [5, '101.25', false]]]],
            $this->plan($exercise->user->routines()->sole()),
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidSets(): array
    {
        return [
            'zero reps' => [['target_reps' => 0], 'exercises.0.sets.0.target_reps', 'The target reps field must be at least 1.'],
            'fractional reps' => [['target_reps' => 7.5], 'exercises.0.sets.0.target_reps', 'The target reps field must be an integer.'],
            'no reps' => [['target_reps' => null], 'exercises.0.sets.0.target_reps', 'The target reps field is required.'],
            'negative weight' => [['target_weight' => -2.5], 'exercises.0.sets.0.target_weight', 'The target weight field must be at least 0.'],
            'three decimals' => [['target_weight' => 80.125], 'exercises.0.sets.0.target_weight', 'The target weight field must have 0-2 decimal places.'],
            'no weight' => [['target_weight' => null], 'exercises.0.sets.0.target_weight', 'The target weight field is required.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidSets')]
    public function test_invalid_target_is_rejected(array $overrides, string $field, string $message)
    {
        $exercise = Exercise::factory()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->from(route('routines.create'))
            ->post(route('routines.store'), [
                'name' => 'Leg day',
                'exercises' => [
                    ['exercise_id' => $exercise->id, 'sets' => [
                        [...['target_reps' => 5, 'target_weight' => 100, 'is_warm_up' => false], ...$overrides],
                    ]],
                ],
            ]);

        $response
            ->assertSessionHasErrors([$field => $message])
            ->assertRedirect(route('routines.create'));

        $this->assertDatabaseEmpty('routines');
    }

    public function test_exercise_without_sets_is_rejected()
    {
        $exercise = Exercise::factory()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->post(route('routines.store'), [
                'name' => 'Leg day',
                'exercises' => [['exercise_id' => $exercise->id, 'sets' => []]],
            ]);

        $response->assertSessionHasErrors(['exercises.0.sets' => 'Give each Exercise at least one Set.']);

        $this->assertDatabaseEmpty('routines');
    }

    public function test_routine_without_a_name_is_rejected()
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('routines.store'), ['name' => '', 'exercises' => []]);

        $response->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->assertDatabaseEmpty('routines');
    }

    public function test_archived_exercise_cannot_be_added()
    {
        $exercise = Exercise::factory()->archived()->create();

        $response = $this
            ->actingAs($exercise->user)
            ->post(route('routines.store'), [
                'name' => 'Leg day',
                'exercises' => [['exercise_id' => $exercise->id, 'sets' => [
                    ['target_reps' => 5, 'target_weight' => 100, 'is_warm_up' => false],
                ]]],
            ]);

        $response->assertSessionHasErrors(['exercises.0.exercise_id' => 'Archived Exercises can\'t be added to a Routine.']);

        $this->assertDatabaseEmpty('routines');
    }

    public function test_another_users_exercise_cannot_be_added()
    {
        $exercise = Exercise::factory()->create();

        $response = $this
            ->actingAs(User::factory()->create())
            ->post(route('routines.store'), [
                'name' => 'Leg day',
                'exercises' => [['exercise_id' => $exercise->id, 'sets' => [
                    ['target_reps' => 5, 'target_weight' => 100, 'is_warm_up' => false],
                ]]],
            ]);

        $response->assertSessionHasErrors(['exercises.0.exercise_id' => 'Choose one of your Exercises.']);

        $this->assertDatabaseEmpty('routines');
    }

    public function test_edit_page_shows_the_plan_and_defers_the_exercises_to_pick_from()
    {
        $owner = User::factory()->create();
        $pullUp = Exercise::factory()->for($owner)->bodyweight()->primaryMuscle(Muscle::Lats)->create(['name' => 'Pull-up', 'equipment' => null]);
        Exercise::factory()->for($owner)->create(['name' => 'Barbell row']);
        Exercise::factory()->for($owner)->archived()->create(['name' => 'Archived curl']);
        Exercise::factory()->create(['name' => 'Someone else\'s squat']);
        $routine = Routine::factory()->for($owner)->create(['name' => 'Pull day']);
        $planned = RoutineExercise::factory()->for($routine)->for($pullUp)->create();
        RoutineSet::factory()->for($planned)->warmUp()->create(['position' => 0, 'target_reps' => 5, 'target_weight' => 0]);
        RoutineSet::factory()->for($planned)->create(['position' => 1, 'target_reps' => 8, 'target_weight' => 12.5]);

        $response = $this
            ->actingAs($owner)
            ->get(route('routines.edit', $routine));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('routines/edit')
            ->where('routine', [
                'id' => $routine->id,
                'name' => 'Pull day',
                'archived' => false,
                'exercises' => [[
                    'exercise' => [
                        'id' => $pullUp->id,
                        'name' => 'Pull-up',
                        'equipment' => null,
                        'is_bodyweight' => true,
                        'archived' => false,
                        'muscles' => [['muscle' => 'lats', 'role' => 'primary']],
                    ],
                    'sets' => [
                        ['target_reps' => 5, 'target_weight' => 0, 'is_warm_up' => true],
                        ['target_reps' => 8, 'target_weight' => 12.5, 'is_warm_up' => false],
                    ],
                ]],
            ])
            ->missing('exercises')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exercises', fn ($exercises) => collect($exercises)->pluck('name')->all() === ['Barbell row', 'Pull-up'])
            )
        );
    }

    public function test_owner_renames_and_replans_a_routine()
    {
        $routine = Routine::factory()->withExercises(3, 4)->create(['name' => 'Push day']);
        $press = Exercise::factory()->for($routine->user)->create(['name' => 'Overhead press']);
        $kept = $routine->exercises[2]->exercise;

        $response = $this
            ->actingAs($routine->user)
            ->put(route('routines.update', $routine), [
                'name' => 'Push day B',
                'exercises' => [
                    ['exercise_id' => $press->id, 'sets' => [
                        ['target_reps' => 6, 'target_weight' => 50, 'is_warm_up' => false],
                    ]],
                    ['exercise_id' => $kept->id, 'sets' => [
                        ['target_reps' => 12, 'target_weight' => 20, 'is_warm_up' => true],
                        ['target_reps' => 10, 'target_weight' => 30, 'is_warm_up' => false],
                    ]],
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'))
            ->assertInertiaFlash('toast.message', 'Routine saved.');

        $routine->refresh();

        $this->assertSame('Push day B', $routine->name);
        $this->assertSame([
            ['exercise' => 'Overhead press', 'sets' => [[6, '50.00', false]]],
            ['exercise' => $kept->name, 'sets' => [[12, '20.00', true], [10, '30.00', false]]],
        ], $this->plan($routine));
        $this->assertDatabaseCount('routine_exercises', 2);
        $this->assertDatabaseCount('routine_sets', 3);
    }

    public function test_exercise_archived_after_planning_can_stay_in_the_routine()
    {
        $archived = Exercise::factory()->archived()->create();
        $routine = Routine::factory()->for($archived->user)->create();
        RoutineExercise::factory()->for($routine)->for($archived)->has(RoutineSet::factory(), 'sets')->create();

        $response = $this
            ->actingAs($routine->user)
            ->put(route('routines.update', $routine), [
                'name' => $routine->name,
                'exercises' => [['exercise_id' => $archived->id, 'sets' => [
                    ['target_reps' => 5, 'target_weight' => 100, 'is_warm_up' => false],
                ]]],
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame([['exercise' => $archived->name, 'sets' => [[5, '100.00', false]]]], $this->plan($routine->refresh()));
    }

    public function test_exercise_archived_after_planning_cannot_be_added_a_second_time()
    {
        $archived = Exercise::factory()->archived()->create();
        $routine = Routine::factory()->for($archived->user)->create();
        RoutineExercise::factory()->for($routine)->for($archived)->has(RoutineSet::factory(), 'sets')->create();
        $set = ['target_reps' => 5, 'target_weight' => 100, 'is_warm_up' => false];

        $response = $this
            ->actingAs($routine->user)
            ->put(route('routines.update', $routine), [
                'name' => $routine->name,
                'exercises' => [
                    ['exercise_id' => $archived->id, 'sets' => [$set]],
                    ['exercise_id' => $archived->id, 'sets' => [$set]],
                ],
            ]);

        $response->assertSessionHasErrors(['exercises.1.exercise_id' => 'Archived Exercises can\'t be added to a Routine.']);
        $response->assertSessionDoesntHaveErrors('exercises.0.exercise_id');

        $this->assertDatabaseCount('routine_exercises', 1);
    }

    public function test_create_page_defers_the_exercises_to_pick_from()
    {
        $exercise = Exercise::factory()->create(['name' => 'Squat']);

        $response = $this
            ->actingAs($exercise->user)
            ->get(route('routines.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('routines/create')
            ->missing('exercises')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('exercises', fn ($exercises) => collect($exercises)->pluck('name')->all() === ['Squat'])
            )
        );
    }

    /**
     * The Routine's plan as Exercise names with [reps, weight, warm-up] per Set, in order.
     *
     * @return array<int, array{exercise: string, sets: array<int, array{0: int, 1: string, 2: bool}>}>
     */
    private function plan(Routine $routine): array
    {
        return $routine->exercises->map(fn ($planned) => [
            'exercise' => $planned->exercise->name,
            'sets' => $planned->sets->map(fn ($set) => [$set->target_reps, $set->target_weight, $set->is_warm_up])->all(),
        ])->all();
    }
}
