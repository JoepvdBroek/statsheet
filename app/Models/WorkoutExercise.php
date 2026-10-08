<?php

namespace App\Models;

use App\Concerns\HasPosition;
use App\Enums\SetKind;
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
     * Add a Set after the others, copying the last non-warm-up Set (or the last Set when all are Warm-ups):
     * its Target is that Set's Actual when done, otherwise its Target, and it is a Drop Set after a Drop Set,
     * otherwise a Working Set. The first Set has no Target.
     */
    public function addSet(): WorkoutSet
    {
        $sets = $this->sets()->get();
        $copied = $sets->last(fn (WorkoutSet $set) => $set->kind->counts()) ?? $sets->last();
        $target = $copied?->targetForNextSet();

        return $this->sets()->create([
            'position' => ($sets->max('position') ?? -1) + 1,
            'target_reps' => $target['reps'] ?? null,
            'target_weight' => $target['weight'] ?? null,
            'kind' => $copied?->kind === SetKind::Drop ? SetKind::Drop : SetKind::Working,
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
