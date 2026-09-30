<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Models\Exercise;
use App\Models\ExerciseMuscle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class ImportCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_only_the_strength_type_exercises_for_the_user()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')
            ->expectsOutputToContain('Imported 7 Exercises for owner@example.com.')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing([
            'Barbell_Bench_Press_-_Medium_Grip',
            'Pullups',
            'Inverted_Row',
            'Barbell_Step_Ups',
            'Barbell_Hip_Thrust',
            'Clean_Shrug',
            'Atlas_Stone_Trainer',
        ], $owner->exercises()->pluck('source_id')->all());
    }

    public function test_keeps_the_name_equipment_and_muscles_of_each_exercise()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $benchPress = $owner->exercises()->where('source_id', 'Barbell_Bench_Press_-_Medium_Grip')->sole();
        $this->assertSame('Barbell Bench Press - Medium Grip', $benchPress->name);
        $this->assertSame(Equipment::Barbell, $benchPress->equipment);
        $this->assertSame([
            'chest' => 'primary',
            'shoulders' => 'secondary',
            'triceps' => 'secondary',
        ], $this->musclesOf($benchPress));
    }

    public function test_a_muscle_the_catalogue_lists_as_both_primary_and_secondary_is_imported_as_primary_only()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $stepUps = $owner->exercises()->where('source_id', 'Barbell_Step_Ups')->sole();
        $this->assertSame([
            'quadriceps' => 'primary',
            'calves' => 'secondary',
            'glutes' => 'secondary',
            'hamstrings' => 'secondary',
        ], $this->musclesOf($stepUps));
    }

    public function test_a_body_only_exercise_is_imported_with_the_bodyweight_flag_off()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $pullups = $owner->exercises()->where('source_id', 'Pullups')->sole();
        $this->assertSame(Equipment::BodyOnly, $pullups->equipment);
        $this->assertFalse($pullups->is_bodyweight);
    }

    public function test_an_exercise_without_equipment_in_the_catalogue_is_imported_without_equipment()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $this->assertNull($owner->exercises()->where('source_id', 'Inverted_Row')->sole()->equipment);
    }

    public function test_imports_no_image_data()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $imported = $owner->exercises()->with('muscles')->get()->toJson();
        $this->assertStringNotContainsString('.jpg', $imported);
        $this->assertStringNotContainsString('images', $imported);
    }

    public function test_running_the_import_again_adds_nothing_twice()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->importFixture('owner@example.com')->assertSuccessful();

        $this->importFixture('owner@example.com')
            ->expectsOutputToContain('Imported 0 Exercises for owner@example.com. Skipped 7 already imported.')
            ->assertSuccessful();

        $this->assertSame(7, $owner->exercises()->count());
        $this->assertDatabaseCount('exercise_muscles', 24);
    }

    public function test_running_the_import_again_keeps_the_owners_edits()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->importFixture('owner@example.com')->assertSuccessful();
        $pullups = $owner->exercises()->where('source_id', 'Pullups')->sole();
        $pullups->update(['name' => 'Pull-up', 'equipment' => Equipment::Other, 'is_bodyweight' => true]);
        $pullups->syncMuscles([Muscle::Lats], [Muscle::Biceps]);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $pullups->refresh();
        $this->assertSame('Pull-up', $pullups->name);
        $this->assertSame(Equipment::Other, $pullups->equipment);
        $this->assertTrue($pullups->is_bodyweight);
        $this->assertSame(['lats' => 'primary', 'biceps' => 'secondary'], $this->musclesOf($pullups));
    }

    public function test_running_the_import_again_does_not_bring_back_an_archived_exercise()
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        Exercise::factory()->for($owner)->archived()->create(['source_id' => 'Pullups']);

        $this->importFixture('owner@example.com')->assertSuccessful();

        $this->assertSame(1, $owner->exercises()->where('source_id', 'Pullups')->count());
        $this->assertSame(0, $owner->exercises()->active()->where('source_id', 'Pullups')->count());
    }

    public function test_another_users_imported_exercises_do_not_stop_the_import()
    {
        User::factory()->create(['email' => 'someone@example.com']);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->importFixture('someone@example.com')->assertSuccessful();

        $this->importFixture('owner@example.com')
            ->expectsOutputToContain('Imported 7 Exercises for owner@example.com.')
            ->assertSuccessful();

        $this->assertSame(7, $owner->exercises()->count());
    }

    public function test_refuses_an_email_address_that_belongs_to_no_user()
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->importFixture('nobody@example.com')
            ->expectsOutputToContain('No user has the email address nobody@example.com.')
            ->assertFailed();

        $this->assertDatabaseEmpty('exercises');
    }

    /**
     * Run the import against the small fixture of free-exercise-db instead of the bundled snapshot.
     */
    private function importFixture(string $email): PendingCommand
    {
        return $this->artisan('app:import-catalogue', [
            'email' => $email,
            '--dataset' => base_path('tests/Fixtures/free-exercise-db.json'),
        ]);
    }

    /**
     * The Muscles an Exercise trains, as Muscle name => role.
     *
     * @return array<string, string>
     */
    private function musclesOf(Exercise $exercise): array
    {
        return $exercise->muscles
            ->mapWithKeys(fn (ExerciseMuscle $muscle) => [$muscle->muscle->value => $muscle->role->value])
            ->all();
    }
}
