<?php

namespace Tests\Feature\Routines;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutineOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('routines.index'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routineRoutes(): array
    {
        return [
            'edit' => ['get', 'routines.edit'],
            'update' => ['put', 'routines.update'],
            'archive' => ['post', 'routines.archive'],
            'restore' => ['post', 'routines.restore'],
        ];
    }

    #[DataProvider('routineRoutes')]
    public function test_another_users_routine_returns_404_and_stays_unchanged(string $method, string $routeName)
    {
        $routine = Routine::factory()->withExercises(1, 2)->archived()->create(['name' => 'Push day']);

        $response = $this
            ->actingAs(User::factory()->create())
            ->{$method}(route($routeName, $routine), [
                'name' => 'Renamed',
                'exercises' => [],
            ]);

        $response->assertNotFound();

        $routine->refresh();
        $this->assertSame('Push day', $routine->name);
        $this->assertNotNull($routine->archived_at);
        $this->assertDatabaseCount('routine_sets', 2);
    }
}
