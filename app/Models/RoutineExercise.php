<?php

namespace App\Models;

use Database\Factories\RoutineExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $routine_id
 * @property int $exercise_id
 * @property int $position 0-based place in the Routine's order
 */
#[Fillable(['exercise_id', 'position'])]
#[WithoutTimestamps]
class RoutineExercise extends Model
{
    /** @use HasFactory<RoutineExerciseFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Routine, $this>
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * The planned Sets, in order.
     *
     * @return HasMany<RoutineSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(RoutineSet::class)->orderBy('position');
    }
}
