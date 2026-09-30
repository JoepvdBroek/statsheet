<?php

namespace App\Models;

use App\Concerns\HasPosition;
use Database\Factories\WorkoutExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workout_id
 * @property int $exercise_id
 * @property int $position 0-based place in the Workout's order
 */
#[Fillable(['exercise_id', 'position'])]
#[WithoutTimestamps]
class WorkoutExercise extends Model
{
    /** @use HasFactory<WorkoutExerciseFactory> */
    use HasFactory, HasPosition;

    /**
     * @return BelongsTo<Workout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * The Sets, in order.
     *
     * @return HasMany<WorkoutSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('position');
    }

    /**
     * Add a Set after the others. Sets added during a Workout have no Target.
     */
    public function addSet(): WorkoutSet
    {
        return $this->sets()->create([
            'position' => ($this->sets()->max('position') ?? -1) + 1,
        ]);
    }

    /**
     * Move a Set to the given 0-based place among this Exercise's Sets.
     */
    public function moveSet(WorkoutSet $moved, int $position): void
    {
        WorkoutSet::reposition($this->sets()->get(), $moved, $position);
    }
}
