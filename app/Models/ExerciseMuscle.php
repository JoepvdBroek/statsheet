<?php

namespace App\Models;

use App\Enums\Muscle;
use App\Enums\MuscleRole;
use Database\Factories\ExerciseMuscleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $exercise_id
 * @property Muscle $muscle
 * @property MuscleRole $role
 */
#[Fillable(['muscle', 'role'])]
#[WithoutTimestamps]
class ExerciseMuscle extends Model
{
    /** @use HasFactory<ExerciseMuscleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'muscle' => Muscle::class,
            'role' => MuscleRole::class,
        ];
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
