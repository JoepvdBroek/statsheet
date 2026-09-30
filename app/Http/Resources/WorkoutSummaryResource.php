<?php

namespace App\Http\Resources;

use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Workout as the history lists it, without its Exercises and Sets.
 *
 * @mixin Workout
 */
class WorkoutSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, status: string, started_at: string, routine: array{id: int, name: string, archived: bool}|null, note: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->isInProgress() ? 'in_progress' : 'finished',
            'started_at' => $this->started_at->toIso8601String(),
            'routine' => $this->routine === null ? null : [
                'id' => $this->routine->id,
                'name' => $this->routine->name,
                'archived' => $this->routine->archived_at !== null,
            ],
            'note' => $this->note,
        ];
    }
}
