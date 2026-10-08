<?php

namespace App\Http\Resources;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Support\ExerciseProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workout
 *
 * @phpstan-import-type Measure from ExerciseProgress
 */
class WorkoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, status: string, started_at: string, finished_at: string|null, routine: array{id: int, name: string, archived: bool}|null, bodyweight: float|null, note: string|null, exercises: array<int, array{id: int, exercise: array<string, mixed>, sets: array<int, array{id: int, target_reps: int|null, target_weight: float|null, actual_reps: int|null, actual_weight: float|null, kind: string, done: bool, meets_target: bool, new_records: list<Measure>}>}>}
     */
    public function toArray(Request $request): array
    {
        $newRecords = ExerciseProgress::newRecordsIn($this->resource);

        return [
            'id' => $this->id,
            'status' => $this->isInProgress() ? 'in_progress' : 'finished',
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'routine' => $this->routine === null ? null : [
                'id' => $this->routine->id,
                'name' => $this->routine->name,
                'archived' => $this->routine->archived_at !== null,
            ],
            'bodyweight' => $this->bodyweight === null ? null : (float) $this->bodyweight,
            'note' => $this->note,
            'exercises' => $this->exercises
                ->map(fn (WorkoutExercise $performed) => [
                    'id' => $performed->id,
                    'exercise' => ExerciseResource::make($performed->exercise)->resolve($request),
                    'sets' => $performed->sets
                        ->map(fn (WorkoutSet $set) => [
                            'id' => $set->id,
                            'target_reps' => $set->target_reps,
                            'target_weight' => $set->target_weight === null ? null : (float) $set->target_weight,
                            'actual_reps' => $set->actual_reps,
                            'actual_weight' => $set->actual_weight === null ? null : (float) $set->actual_weight,
                            'kind' => $set->kind->value,
                            'done' => $set->isDone(),
                            'meets_target' => $set->meetsTarget(),
                            'new_records' => $newRecords[$set->id] ?? [],
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
