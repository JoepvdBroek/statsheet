<?php

namespace App\Http\Resources;

use App\Models\WeeklyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Weekly Review with its generation status and its content, if any has been written.
 *
 * @mixin WeeklyReview
 */
class WeeklyReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, week: string, status: string, summary: string|null, muscle_notes: list<array{muscle: string, note: string}>|null, advice: list<string>|null, model: string|null, generated_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week' => $this->week->toDateString(),
            'status' => $this->status->value,
            'summary' => $this->summary,
            'muscle_notes' => $this->muscle_notes,
            'advice' => $this->advice,
            'model' => $this->model,
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
