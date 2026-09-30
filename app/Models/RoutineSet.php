<?php

namespace App\Models;

use Database\Factories\RoutineSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $routine_exercise_id
 * @property int $position 0-based place among the Exercise's Sets
 * @property int $target_reps
 * @property string $target_weight Target weight in kg; added load for a Bodyweight Exercise
 * @property bool $is_warm_up Whether the Set is a Warm-up Set, which never counts
 */
#[Fillable(['position', 'target_reps', 'target_weight', 'is_warm_up'])]
#[WithoutTimestamps]
class RoutineSet extends Model
{
    /** @use HasFactory<RoutineSetFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_warm_up' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_reps' => 'integer',
            'target_weight' => 'decimal:2',
            'is_warm_up' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RoutineExercise, $this>
     */
    public function routineExercise(): BelongsTo
    {
        return $this->belongsTo(RoutineExercise::class);
    }
}
