<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\VolumePerWeek;
use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\AssertsDatabaseUnchanged;
use Tests\Concerns\PerformsExercises;
use Tests\TestCase;

class VolumePerWeekTest extends TestCase
{
    use AssertsDatabaseUnchanged, PerformsExercises, RefreshDatabase;

    public function test_it_gives_the_owners_volume_per_muscle_for_each_week_of_the_range()
    {
        $owner = User::factory()->create(['timezone' => 'America/New_York']);
        $benchPress = Exercise::factory()->for($owner)->primaryMuscle(Muscle::Chest)->withSecondaryMuscle(Muscle::Triceps)->create();
        $sundayNightInNewYork = '2026-09-21 03:30:00';
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => $sundayNightInNewYork]), $benchPress, [$this->doneSet(10, '100.00')]);
        $this->perform(Workout::factory()->for($owner)->finished()->create(['started_at' => '2026-09-29 12:00:00']), $benchPress, [$this->doneSet(5, '100.00')]);
        $theirSquat = Exercise::factory()->primaryMuscle(Muscle::Quadriceps)->create();
        $this->perform(Workout::factory()->for($theirSquat->user)->finished()->create(['started_at' => '2026-09-16 12:00:00']), $theirSquat, [$this->doneSet(5, '200.00')]);

        $result = $this->assertDatabaseUnchangedBy(
            fn () => (new VolumePerWeek($owner))->handle(new Request(['from' => '2026-09-16', 'to' => '2026-09-22'])),
        );

        $this->assertSame([
            '2026-09-14' => ['chest' => 1000, 'triceps' => 500],
            '2026-09-21' => [],
        ], json_decode((string) $result, true));
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidRanges(): array
    {
        return [
            'missing' => [[]],
            'ending before it starts' => [['from' => '2026-09-21', 'to' => '2026-09-14']],
            'longer than two years' => [['from' => '2024-01-01', 'to' => '2026-09-14']],
        ];
    }

    #[DataProvider('invalidRanges')]
    public function test_it_needs_a_range_of_at_most_two_years(array $arguments)
    {
        $this->expectException(ValidationException::class);

        (new VolumePerWeek(User::factory()->create()))->handle(new Request($arguments));
    }
}
