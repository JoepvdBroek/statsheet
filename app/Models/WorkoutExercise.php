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
use Illuminate\Support\Facades\DB;

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
     * Give the Exercise a Set per planned Set, Pre-filled from the same Set when the Exercise was last performed before
     * this Workout: that Set's kind and carried-over Target, or the planned kind and Target when it has none.
     *
     * @param  list<array{target_reps: int|null, target_weight: string|null, kind: SetKind}>  $plan
     */
    public function preFill(array $plan): void
    {
        $lastTime = $this->exercise->lastPerformance($this->workout->started_at)?->sets;

        foreach ($plan as $position => $planned) {
            $lastTimeSet = $lastTime?->get($position);
            $carriedOver = $lastTimeSet?->carriedOverTarget();

            $this->sets()->create([
                'position' => $position,
                ...($carriedOver === null ? $planned : [
                    'target_reps' => $carriedOver['reps'],
                    'target_weight' => $carriedOver['weight'],
                    'kind' => $lastTimeSet->kind,
                ]),
            ]);
        }
    }

    /**
     * Swap in another Exercise in this place: its Sets keep their count and kinds as the plan, Pre-filled for it.
     */
    public function swapFor(Exercise $exercise): void
    {
        DB::transaction(function () use ($exercise) {
            $plan = $this->sets()->get()
                ->map(fn (WorkoutSet $set) => ['target_reps' => null, 'target_weight' => null, 'kind' => $set->kind])
                ->all();

            $this->sets()->delete();
            $this->exercise()->associate($exercise)->save();
            $this->preFill($plan);
        });
    }

    /**
     * Whether any of its Sets is done.
     */
    public function hasDoneSet(): bool
    {
        return $this->sets()->whereNotNull('actual_reps')->exists();
    }

    /**
     * Move a Set to the given 0-based place among this Exercise's Sets.
     */
    public function moveSet(WorkoutSet $moved, int $position): void
    {
        WorkoutSet::reposition($this->sets()->get(), $moved, $position);
    }
}
