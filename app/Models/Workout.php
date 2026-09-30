<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at Empty while the Workout is in progress
 * @property string|null $bodyweight The owner's Bodyweight in kg when the Workout started
 * @property string|null $note The Workout Note: context the numbers don't show
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['started_at', 'bodyweight', 'note'])]
class Workout extends Model
{
    /** @use HasFactory<WorkoutFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'bodyweight' => 'decimal:2',
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
     * The performed Exercises, in the order they are trained.
     *
     * @return HasMany<WorkoutExercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('position');
    }

    /**
     * Every Set of every Exercise in the Workout.
     *
     * @return HasManyThrough<WorkoutSet, WorkoutExercise, $this>
     */
    public function sets(): HasManyThrough
    {
        return $this->hasManyThrough(WorkoutSet::class, WorkoutExercise::class);
    }

    /**
     * Workouts not finished yet. An owner has at most one.
     *
     * @param  Builder<Workout>  $query
     */
    #[Scope]
    protected function inProgress(Builder $query): void
    {
        $query->whereNull('finished_at');
    }

    /**
     * Whether the Workout is still in progress; otherwise it is finished.
     */
    public function isInProgress(): bool
    {
        return $this->finished_at === null;
    }

    /**
     * Finish the Workout. Finishing it again keeps the first finish time.
     */
    public function finish(): void
    {
        $this->finished_at ??= now();
        $this->save();
    }

    /**
     * Add an Exercise after the others, with one Set to log.
     */
    public function addExercise(Exercise $exercise): WorkoutExercise
    {
        $workoutExercise = $this->exercises()->create([
            'exercise_id' => $exercise->id,
            'position' => ($this->exercises()->max('position') ?? -1) + 1,
        ]);

        $workoutExercise->addSet();

        return $workoutExercise;
    }

    /**
     * Move an Exercise to the given 0-based place in the order.
     */
    public function moveExercise(WorkoutExercise $moved, int $position): void
    {
        WorkoutExercise::reposition($this->exercises()->get(), $moved, $position);
    }
}
