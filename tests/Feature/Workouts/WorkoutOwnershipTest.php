<?php

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkoutOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('workouts.index'))->assertRedirect(route('login'));
        $this->post(route('workouts.store'))->assertRedirect(route('login'));
        $this->assertDatabaseEmpty('workouts');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function workoutRoutes(): array
    {
        return [
            'show' => ['get', 'workouts.show', null],
            'update note' => ['patch', 'workouts.update', null],
            'finish' => ['post', 'workouts.finish', null],
            'delete' => ['delete', 'workouts.destroy', null],
            'update routine' => ['put', 'workouts.routine.update', null],
            'add exercise' => ['post', 'workouts.exercises.store', null],
            'move exercise' => ['put', 'workouts.exercises.move', 'exercise'],
            'remove exercise' => ['delete', 'workouts.exercises.destroy', 'exercise'],
            'add set' => ['post', 'workouts.sets.store', 'exercise'],
            'update set' => ['patch', 'workouts.sets.update', 'set'],
            'mark set done' => ['put', 'workouts.sets.done', 'set'],
            'mark set not done' => ['delete', 'workouts.sets.undone', 'set'],
            'move set' => ['put', 'workouts.sets.move', 'set'],
            'remove set' => ['delete', 'workouts.sets.destroy', 'set'],
        ];
    }

    #[DataProvider('workoutRoutes')]
    public function test_another_users_workout_returns_404_and_stays_unchanged(string $method, string $routeName, ?string $child)
    {
        $workout = Workout::factory()->withExercises(2, 2)->create(['note' => 'Mine']);
        $exercise = $workout->exercises[1];
        $set = $exercise->sets[1];
        $parameters = match ($child) {
            'exercise' => [$workout, $exercise],
            'set' => [$workout, $set],
            default => [$workout],
        };

        $response = $this
            ->actingAs(User::factory()->create())
            ->{$method}(route($routeName, $parameters), [
                'note' => 'Theirs',
                'position' => 0,
                'actual_reps' => 5,
                'actual_weight' => 50,
                'is_warm_up' => true,
            ]);

        $response->assertNotFound();

        $workout->refresh();
        $this->assertSame('Mine', $workout->note);
        $this->assertNull($workout->finished_at);
        $this->assertSame([0, 1], $workout->exercises()->pluck('position')->all());
        $this->assertDatabaseCount('workout_sets', 4);
        $this->assertSame(1, $set->refresh()->position);
        $this->assertFalse($set->isDone());
        $this->assertFalse($set->is_warm_up);
    }
}
