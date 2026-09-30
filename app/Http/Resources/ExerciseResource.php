<?php

namespace App\Http\Resources;

use App\Models\Exercise;
use App\Models\ExerciseMuscle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exercise
 */
class ExerciseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, equipment: string|null, is_bodyweight: bool, archived: bool, muscles: array<int, array{muscle: string, role: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'equipment' => $this->equipment?->value,
            'is_bodyweight' => $this->is_bodyweight,
            'archived' => $this->archived_at !== null,
            'muscles' => $this->muscles
                ->map(fn (ExerciseMuscle $trained) => ['muscle' => $trained->muscle->value, 'role' => $trained->role->value])
                ->all(),
        ];
    }
}
