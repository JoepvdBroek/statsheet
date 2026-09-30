<?php

namespace Tests\Feature\Exercises;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExerciseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_an_exercise_with_primary_and_secondary_muscles()
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->post(route('exercises.store'), [
                'name' => 'Barbell bench press',
                'equipment' => 'barbell',
                'is_bodyweight' => false,
                'primary_muscles' => ['chest'],
                'secondary_muscles' => ['shoulders', 'triceps'],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('exercises.index'))
            ->assertInertiaFlash('toast.message', 'Exercise created.');

        $this->assertDatabaseHas('exercises', [
            'user_id' => $owner->id,
            'name' => 'Barbell bench press',
            'equipment' => 'barbell',
            'is_bodyweight' => false,
            'source_id' => null,
            'archived_at' => null,
        ]);
        $this->assertDatabaseCount('exercise_muscles', 3);
        $this->assertDatabaseHas('exercise_muscles', ['muscle' => 'chest', 'role' => 'primary']);
        $this->assertDatabaseHas('exercise_muscles', ['muscle' => 'shoulders', 'role' => 'secondary']);
        $this->assertDatabaseHas('exercise_muscles', ['muscle' => 'triceps', 'role' => 'secondary']);
    }

    public function test_edit_page_shows_the_exercise_with_its_muscles_and_the_choices()
    {
        $exercise = Exercise::factory()
            ->bodyweight()
            ->primaryMuscle(Muscle::Lats)
            ->withSecondaryMuscle(Muscle::MiddleBack)
            ->create(['name' => 'Pull-up', 'equipment' => Equipment::BodyOnly]);

        $response = $this
            ->actingAs($exercise->user)
            ->get(route('exercises.edit', $exercise));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('exercises/edit')
            ->where('exercise', [
                'id' => $exercise->id,
                'name' => 'Pull-up',
                'equipment' => 'body only',
                'is_bodyweight' => true,
                'archived' => false,
                'muscles' => [
                    ['muscle' => 'lats', 'role' => 'primary'],
                    ['muscle' => 'middle back', 'role' => 'secondary'],
                ],
            ])
            ->has('muscles', 17)
            ->where('muscles.10', 'lower back')
            ->has('equipment', 12)
        );
    }

    public function test_owner_edits_an_exercise_and_replaces_its_muscles()
    {
        $exercise = Exercise::factory()
            ->primaryMuscle(Muscle::Chest)
            ->withSecondaryMuscle(Muscle::Triceps)
            ->create(['name' => 'Bench press', 'equipment' => 'barbell']);

        $response = $this
            ->actingAs($exercise->user)
            ->put(route('exercises.update', $exercise), [
                'name' => 'Dumbbell bench press',
                'equipment' => 'dumbbell',
                'is_bodyweight' => false,
                'primary_muscles' => ['chest', 'triceps'],
                'secondary_muscles' => [],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('exercises.index'))
            ->assertInertiaFlash('toast.message', 'Exercise saved.');

        $exercise->refresh();

        $this->assertSame('Dumbbell bench press', $exercise->name);
        $this->assertSame('dumbbell', $exercise->equipment->value);
        $this->assertSame(
            [['muscle' => 'chest', 'role' => 'primary'], ['muscle' => 'triceps', 'role' => 'primary']],
            $exercise->muscles->map(fn ($trained) => ['muscle' => $trained->muscle->value, 'role' => $trained->role->value])->all(),
        );
    }

    public function test_editing_an_exercise_applies_the_same_validation()
    {
        $exercise = Exercise::factory()->primaryMuscle(Muscle::Chest)->create();

        $response = $this
            ->actingAs($exercise->user)
            ->from(route('exercises.edit', $exercise))
            ->put(route('exercises.update', $exercise), [
                ...$this->validExercise(),
                'primary_muscles' => [],
            ]);

        $response
            ->assertSessionHasErrors(['primary_muscles' => 'Choose at least one primary Muscle.'])
            ->assertRedirect(route('exercises.edit', $exercise));

        $this->assertSame(['chest'], $exercise->muscles()->pluck('muscle')->map->value->all());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function invalidExercises(): array
    {
        return [
            'no name' => [['name' => ''], 'name', 'The name field is required.'],
            'no primary muscle' => [['primary_muscles' => []], 'primary_muscles', 'Choose at least one primary Muscle.'],
            'muscle chosen twice' => [['primary_muscles' => ['lats', 'lats']], 'primary_muscles.0', 'The primary_muscles.0 field has a duplicate value.'],
            'unknown muscle' => [['primary_muscles' => ['arms']], 'primary_muscles.0', 'The selected primary_muscles.0 is invalid.'],
            'muscle both primary and secondary' => [
                ['primary_muscles' => ['chest'], 'secondary_muscles' => ['triceps', 'chest']],
                'secondary_muscles.1',
                'A Muscle can\'t be both primary and secondary.',
            ],
            'unknown equipment' => [['equipment' => 'trap bar'], 'equipment', 'The selected equipment is invalid.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidExercises')]
    public function test_invalid_exercise_is_rejected(array $overrides, string $field, string $message)
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->from(route('exercises.create'))
            ->post(route('exercises.store'), [...$this->validExercise(), ...$overrides]);

        $response
            ->assertSessionHasErrors([$field => $message])
            ->assertRedirect(route('exercises.create'));

        $this->assertDatabaseEmpty('exercises');
    }

    /**
     * @return array<string, mixed>
     */
    private function validExercise(): array
    {
        return [
            'name' => 'Pull-up',
            'equipment' => null,
            'is_bodyweight' => true,
            'primary_muscles' => ['lats'],
            'secondary_muscles' => ['biceps'],
        ];
    }
}
