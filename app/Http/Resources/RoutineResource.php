<?php

namespace App\Http\Resources;

use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\RoutineSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Routine
 */
class RoutineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The Last Done is included only when the Routines were queried with it.
     *
     * @return array{id: int, name: string, archived: bool, last_done?: string|null, exercises: array<int, array{exercise: array<string, mixed>, sets: array<int, array{target_reps: int, target_weight: float, is_warm_up: bool}>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'archived' => $this->archived_at !== null,
            'last_done' => $this->whenHas('last_done_at', fn () => $this->last_done_at?->toIso8601String()),
            'exercises' => $this->exercises
                ->map(fn (RoutineExercise $planned) => [
                    'exercise' => ExerciseResource::make($planned->exercise)->resolve($request),
                    'sets' => $planned->sets
                        ->map(fn (RoutineSet $set) => [
                            'target_reps' => $set->target_reps,
                            'target_weight' => (float) $set->target_weight,
                            'is_warm_up' => $set->is_warm_up,
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
