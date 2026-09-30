<?php

namespace Tests\Feature\Exercises;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExerciseOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function exerciseRoutes(): array
    {
        return [
            'show' => ['get', 'exercises.show'],
            'edit' => ['get', 'exercises.edit'],
            'update' => ['put', 'exercises.update'],
            'destroy' => ['delete', 'exercises.destroy'],
            'restore' => ['post', 'exercises.restore'],
        ];
    }

    #[DataProvider('exerciseRoutes')]
    public function test_another_users_exercise_returns_404_and_stays_unchanged(string $method, string $routeName)
    {
        $exercise = Exercise::factory()->archived()->create(['name' => 'Squat']);

        $response = $this
            ->actingAs(User::factory()->create())
            ->{$method}(route($routeName, $exercise), [
                'name' => 'Renamed',
                'primary_muscles' => ['chest'],
            ]);

        $response->assertNotFound();

        $exercise->refresh();
        $this->assertSame('Squat', $exercise->name);
        $this->assertNotNull($exercise->archived_at);
    }
}
