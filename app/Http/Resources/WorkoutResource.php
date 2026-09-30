<?php

namespace App\Http\Resources;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workout
 */
class WorkoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, status: string, started_at: string, finished_at: string|null, bodyweight: float|null, note: string|null, exercises: array<int, array{id: int, exercise: array<string, mixed>, sets: array<int, array{id: int, target_reps: int|null, target_weight: float|null, actual_reps: int|null, actual_weight: float|null, is_warm_up: bool, done: bool, meets_target: bool}>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->isInProgress() ? 'in_progress' : 'finished',
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
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
                            'is_warm_up' => $set->is_warm_up,
                            'done' => $set->isDone(),
                            'meets_target' => $set->meetsTarget(),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
