<?php

namespace App\Models;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Enums\MuscleRole;
use Carbon\CarbonImmutable;
use Database\Factories\ExerciseFactory;
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
 * @property Equipment|null $equipment
 * @property bool $is_bodyweight Whether a set's weight means added load on top of Bodyweight
 * @property string|null $source_id The free-exercise-db id of an imported Exercise
 * @property CarbonImmutable|null $archived_at When the Exercise was taken out of use, hiding it from pickers
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'equipment', 'is_bodyweight', 'source_id'])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_bodyweight' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment' => Equipment::class,
            'is_bodyweight' => 'boolean',
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
     * The Muscles this Exercise trains, primary ones first.
     *
     * @return HasMany<ExerciseMuscle, $this>
     */
    public function muscles(): HasMany
    {
        return $this->hasMany(ExerciseMuscle::class)->orderBy('role')->orderBy('id');
    }

    /**
     * The places Routines plan this Exercise.
     *
     * @return HasMany<RoutineExercise, $this>
     */
    public function routineExercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class);
    }

    /**
     * The places Workouts performed this Exercise.
     *
     * @return HasMany<WorkoutExercise, $this>
     */
    public function workoutExercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class);
    }

    /**
     * Its first occurrence in the most recent Workout containing it that started before the given moment, with its Sets.
     * Empty when it wasn't trained before then.
     */
    public function lastPerformance(CarbonImmutable $before): ?WorkoutExercise
    {
        return $this->workoutExercises()
            ->select('workout_exercises.*')
            ->join('workouts', 'workouts.id', '=', 'workout_exercises.workout_id')
            ->where('workouts.started_at', '<', $before)
            ->orderByDesc('workouts.started_at')
            ->orderByDesc('workouts.id')
            ->orderBy('workout_exercises.position')
            ->with('sets')
            ->first();
    }

    /**
     * Exercises in use, which pickers offer.
     *
     * @param  Builder<Exercise>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Exercises taken out of use, hidden from pickers but restorable.
     *
     * @param  Builder<Exercise>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /**
     * Exercises that train the Muscle, as primary or secondary.
     *
     * @param  Builder<Exercise>  $query
     */
    #[Scope]
    protected function trains(Builder $query, Muscle $muscle): void
    {
        $query->whereHas('muscles', fn (Builder $muscles) => $muscles->where('muscle', $muscle));
    }

    /**
     * Whether a Routine or Workout uses this Exercise, so deleting it must archive it instead.
     *
     * An archived Routine still counts, because it can be restored.
     */
    public function isInUse(): bool
    {
        return $this->routineExercises()->exists() || $this->workoutExercises()->exists();
    }

    /**
     * Take the Exercise out of use: removed when nothing uses it, archived otherwise.
     *
     * @return bool Whether the Exercise was archived rather than deleted
     */
    public function retire(): bool
    {
        if ($this->isInUse()) {
            $this->archived_at = now();
            $this->save();

            return true;
        }

        $this->delete();

        return false;
    }

    /**
     * Put an archived Exercise back in use.
     */
    public function restore(): void
    {
        $this->archived_at = null;
        $this->save();
    }

    /**
     * Replace the Muscles this Exercise trains.
     *
     * @param  array<int, Muscle>  $primary
     * @param  array<int, Muscle>  $secondary
     */
    public function syncMuscles(array $primary, array $secondary = []): void
    {
        $this->muscles()->delete();

        $this->muscles()->createMany([
            ...array_map(fn (Muscle $muscle) => ['muscle' => $muscle, 'role' => MuscleRole::Primary], $primary),
            ...array_map(fn (Muscle $muscle) => ['muscle' => $muscle, 'role' => MuscleRole::Secondary], $secondary),
        ]);
    }
}
