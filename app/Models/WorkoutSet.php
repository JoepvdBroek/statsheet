<?php

namespace App\Models;

use App\Concerns\HasPosition;
use App\Enums\SetKind;
use Database\Factories\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Set is done exactly when it has an Actual; the Actual's reps and weight are empty or present together.
 *
 * @property int $id
 * @property int $workout_exercise_id
 * @property int $position 0-based place among the Exercise's Sets
 * @property int|null $target_reps
 * @property string|null $target_weight Target weight in kg; added load for a Bodyweight Exercise
 * @property int|null $actual_reps
 * @property string|null $actual_weight Actual weight in kg; added load for a Bodyweight Exercise
 * @property SetKind $kind Whether the Set is a Working, Warm-up or Drop Set; a Warm-up Set never counts
 */
#[Fillable(['position', 'target_reps', 'target_weight', 'actual_reps', 'actual_weight', 'kind'])]
#[WithoutTimestamps]
class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory, HasPosition;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => SetKind::Working->value,
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
            'actual_reps' => 'integer',
            'actual_weight' => 'decimal:2',
            'kind' => SetKind::class,
        ];
    }

    /**
     * @return BelongsTo<WorkoutExercise, $this>
     */
    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }

    /**
     * Whether the Set was done, which is exactly when it has an Actual.
     */
    public function isDone(): bool
    {
        return $this->actual_reps !== null;
    }

    /**
     * Whether a done Set's reps and weight are both at or above its Target. A Set without a Target meets it.
     */
    public function meetsTarget(): bool
    {
        if (! $this->isDone()) {
            return false;
        }

        if ($this->target_reps === null) {
            return true;
        }

        return $this->actual_reps >= $this->target_reps
            && (float) $this->actual_weight >= (float) $this->target_weight;
    }

    /**
     * The Target this Set hands to the same Set the next time its Exercise is trained (Pre-fill):
     * its Actual if it met its Target or had none, otherwise its Target. Empty when it has neither.
     *
     * @return array{reps: int, weight: string}|null
     */
    public function carriedOverTarget(): ?array
    {
        if ($this->meetsTarget()) {
            return ['reps' => $this->actual_reps, 'weight' => $this->actual_weight];
        }

        if ($this->target_reps !== null) {
            return ['reps' => $this->target_reps, 'weight' => $this->target_weight];
        }

        return null;
    }

    /**
     * The Target this Set gives the Routine when the Routine is updated from its Workout:
     * its Target, or its Actual when it had no Target. Empty when it has neither.
     *
     * @return array{reps: int, weight: string}|null
     */
    public function plannedTarget(): ?array
    {
        if ($this->target_reps !== null) {
            return ['reps' => $this->target_reps, 'weight' => $this->target_weight];
        }

        if ($this->isDone()) {
            return ['reps' => $this->actual_reps, 'weight' => $this->actual_weight];
        }

        return null;
    }

    /**
     * The Target a Set added after this one during the Workout copies: its Actual when done, otherwise its Target.
     * Empty when it has neither.
     *
     * @return array{reps: int, weight: string}|null
     */
    public function targetForNextSet(): ?array
    {
        if ($this->isDone()) {
            return ['reps' => $this->actual_reps, 'weight' => $this->actual_weight];
        }

        if ($this->target_reps !== null) {
            return ['reps' => $this->target_reps, 'weight' => $this->target_weight];
        }

        return null;
    }

    /**
     * The Actual that marking the Set done logs: what was typed, falling back to the Target's values.
     * A Bodyweight Exercise with no weight typed or planned logs no added load. Empty values are left for the caller to reject.
     *
     * @return array{reps: int|null, weight: string|null}
     */
    public function actualWhenMarkedDone(?int $typedReps, ?string $typedWeight): array
    {
        return [
            'reps' => $typedReps ?? $this->target_reps,
            'weight' => $typedWeight ?? $this->target_weight ?? ($this->workoutExercise->exercise->is_bodyweight ? '0.00' : null),
        ];
    }

    /**
     * Record the reps × weight performed, which marks the Set done.
     */
    public function logActual(int $reps, string $weight): void
    {
        $this->actual_reps = $reps;
        $this->actual_weight = $weight;
        $this->save();
    }

    /**
     * Remove the Actual, which marks the Set not done.
     */
    public function clearActual(): void
    {
        $this->actual_reps = null;
        $this->actual_weight = null;
        $this->save();
    }
}
