<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoutineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property CarbonImmutable|null $archived_at When the Routine was taken out of use, hiding it from planning
 * @property CarbonImmutable|null $last_done_at The Routine's Last Done, only when queried withLastDone()
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name'])]
class Routine extends Model
{
    /** @use HasFactory<RoutineFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The planned Exercises, in the order they are trained.
     *
     * @return HasMany<RoutineExercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class)->orderBy('position');
    }

    /**
     * The Workouts started from the Routine.
     *
     * @return HasMany<Workout, $this>
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /**
     * Add each Routine's Last Done as `last_done_at`: the start of the owner's latest finished Workout from it,
     * or null when it was never done.
     *
     * @param  Builder<Routine>  $query
     */
    #[Scope]
    protected function withLastDone(Builder $query): void
    {
        $query
            ->withMax(['workouts as last_done_at' => fn (Builder $workouts) => $workouts
                ->whereNotNull('workouts.finished_at')
                ->whereColumn('workouts.user_id', 'routines.user_id'),
            ], 'started_at')
            ->withCasts(['last_done_at' => 'datetime']);
    }

    /**
     * Routines in use, which the list shows.
     *
     * @param  Builder<Routine>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Archived Routines, hidden from the list but restorable.
     *
     * @param  Builder<Routine>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Take the Routine out of use. Routines are never deleted, so Workouts started from them keep their link.
     */
    public function archive(): void
    {
        $this->archived_at = now();
        $this->save();
    }

    /**
     * Put an archived Routine back in use.
     */
    public function restore(): void
    {
        $this->archived_at = null;
        $this->save();
    }

    /**
     * Replace the planned Exercises and their Sets, keeping the given order.
     *
     * @param  list<array{exercise_id: int, sets: list<array{target_reps: int, target_weight: string, is_warm_up: bool}>}>  $exercises
     */
    public function syncExercises(array $exercises): void
    {
        $this->exercises()->delete();

        foreach ($exercises as $exercisePosition => $planned) {
            $routineExercise = $this->exercises()->create([
                'exercise_id' => $planned['exercise_id'],
                'position' => $exercisePosition,
            ]);

            foreach ($planned['sets'] as $setPosition => $set) {
                $routineExercise->sets()->create([...$set, 'position' => $setPosition]);
            }
        }
    }
}
