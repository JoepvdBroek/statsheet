<?php

namespace App\Models;

use App\Enums\SetKind;
use App\Support\Supersets;
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
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $routine_id The Routine the Workout was started from; empty when started empty
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at Empty while the Workout is in progress
 * @property string|null $bodyweight The owner's Bodyweight in kg when the Workout started
 * @property string|null $note The Workout Note: context the numbers don't show
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['routine_id', 'started_at', 'bodyweight', 'note'])]
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
     * The Routine the Workout was started from. Its plan was copied in at the start and is never read through this link.
     *
     * @return BelongsTo<Routine, $this>
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
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
     * Workouts started from the first moment up to the second.
     *
     * @param  Builder<Workout>  $query
     */
    #[Scope]
    protected function startedBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->where('started_at', '>=', $from->utc())->where('started_at', '<', $until->utc());
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
     * The Workout's Exercises in order as a Routine plans them, with their Supersets, each Set with what was lifted as its Target (its Actual
     * when done, otherwise its Target) and its kind. Sets with neither are dropped, and so is an Exercise left without Sets; the Supersets
     * are then normalised again, so one left with a single member is dissolved.
     *
     * @return list<array{exercise_id: int, superset: int|null, sets: list<array{target_reps: int, target_weight: string, kind: SetKind}>}>
     */
    public function plannedExercises(): array
    {
        $planned = $this->exercises()->with('sets')->get()
            ->map(fn (WorkoutExercise $performed) => [
                'exercise_id' => $performed->exercise_id,
                'superset' => $performed->superset,
                'sets' => array_values($performed->sets
                    ->map(fn (WorkoutSet $set) => ($target = $set->carriedOverTarget()) === null ? null : [
                        'target_reps' => $target['reps'],
                        'target_weight' => $target['weight'],
                        'kind' => $set->kind,
                    ])
                    ->filter()
                    ->all()),
            ])
            ->filter(fn (array $planned) => $planned['sets'] !== [])
            ->values()
            ->all();

        $supersets = Supersets::normalise(array_column($planned, 'superset'));

        return array_map(fn (array $exercise, ?int $superset) => [...$exercise, 'superset' => $superset], $planned, $supersets);
    }

    /**
     * Add an Exercise after the others, outside any Superset, with one Set to log.
     */
    public function addExercise(Exercise $exercise): WorkoutExercise
    {
        $workoutExercise = $this->exercises()->create([
            'exercise_id' => $exercise->id,
            'position' => ($this->exercises()->max('position') ?? -1) + 1,
            'superset' => null,
        ]);

        $workoutExercise->addSet();

        return $workoutExercise;
    }

    /**
     * Remove an Exercise and its Sets. A Superset left with a single member is dissolved, and the others are numbered again.
     */
    public function removeExercise(WorkoutExercise $removed): void
    {
        DB::transaction(function () use ($removed) {
            $removed->delete();
            $this->normaliseSupersets();
        });
    }

    /**
     * Number the Supersets 1, 2, … in order again and dissolve a Superset with a single member.
     */
    public function normaliseSupersets(): void
    {
        $exercises = array_values($this->exercises()->get()->all());
        $supersets = Supersets::normalise(array_map(fn (WorkoutExercise $performed) => $performed->superset, $exercises));

        foreach ($exercises as $index => $performed) {
            $performed->superset = $supersets[$index];
            $performed->save();
        }
    }

    /**
     * Move an Exercise to the given 0-based place in the order.
     */
    public function moveExercise(WorkoutExercise $moved, int $position): void
    {
        WorkoutExercise::reposition($this->exercises()->get(), $moved, $position);
    }
}
