<?php

namespace App\Console\Commands;

use App\Enums\Muscle;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('app:import-catalogue {email : The email address of the user who gets the Exercises} {--dataset= : The free-exercise-db JSON file to import, instead of the bundled snapshot}')]
#[Description('Import the strength-type Exercises of free-exercise-db for a user')]
class ImportCatalogue extends Command
{
    /**
     * The free-exercise-db categories whose exercises are strength training.
     */
    private const array STRENGTH_CATEGORIES = ['strength', 'powerlifting', 'olympic weightlifting', 'strongman'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user has the email address {$email}.");

            return self::FAILURE;
        }

        /** @var array<int, array{id: string, name: string, category: string, equipment: string|null, primaryMuscles: array<int, string>, secondaryMuscles: array<int, string>}> $catalogue */
        $catalogue = File::json($this->option('dataset') ?? database_path('data/free-exercise-db.json'), JSON_THROW_ON_ERROR);

        $alreadyImported = $user->exercises()->whereNotNull('source_id')->pluck('source_id')->flip();

        [$imported, $skipped] = DB::transaction(function () use ($catalogue, $user, $alreadyImported): array {
            $imported = 0;
            $skipped = 0;

            foreach ($catalogue as $entry) {
                if (! in_array($entry['category'], self::STRENGTH_CATEGORIES, true)) {
                    continue;
                }

                if ($alreadyImported->has($entry['id'])) {
                    $skipped++;

                    continue;
                }

                $exercise = $user->exercises()->create([
                    'name' => $entry['name'],
                    'equipment' => $entry['equipment'],
                    'source_id' => $entry['id'],
                ]);

                $exercise->syncMuscles(
                    array_map(Muscle::from(...), $entry['primaryMuscles']),
                    array_map(Muscle::from(...), array_values(array_diff($entry['secondaryMuscles'], $entry['primaryMuscles']))),
                );

                $imported++;
            }

            return [$imported, $skipped];
        });

        $this->components->info("Imported {$imported} Exercises for {$user->email}.".($skipped > 0 ? " Skipped {$skipped} already imported." : ''));

        return self::SUCCESS;
    }
}
