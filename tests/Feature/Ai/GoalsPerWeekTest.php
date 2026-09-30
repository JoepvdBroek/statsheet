<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\GoalsPerWeek;
use App\Enums\Muscle;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\AssertsDatabaseUnchanged;
use Tests\TestCase;

class GoalsPerWeekTest extends TestCase
{
    use AssertsDatabaseUnchanged, RefreshDatabase;

    public function test_it_gives_the_owners_goals_in_force_in_each_week_of_the_range()
    {
        $owner = User::factory()->create();
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '5000.00', 'effective_week' => '2026-09-07']);
        Goal::factory()->for($owner)->create(['muscle' => Muscle::Chest, 'weekly_minimum' => '6000.00', 'effective_week' => '2026-09-21']);
        Goal::factory()->for($owner)->removed()->create(['muscle' => Muscle::Chest, 'effective_week' => '2026-09-28']);
        Goal::factory()->create(['muscle' => Muscle::Lats, 'weekly_minimum' => '3000.00', 'effective_week' => '2026-09-07']);

        $result = $this->assertDatabaseUnchangedBy(
            fn () => (new GoalsPerWeek($owner))->handle(new Request(['from' => '2026-09-01', 'to' => '2026-09-30'])),
        );

        $this->assertSame([
            '2026-08-31' => [],
            '2026-09-07' => ['chest' => 5000],
            '2026-09-14' => ['chest' => 5000],
            '2026-09-21' => ['chest' => 6000],
            '2026-09-28' => [],
        ], json_decode((string) $result, true));
    }
}
