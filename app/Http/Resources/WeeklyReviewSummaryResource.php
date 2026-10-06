<?php

namespace App\Http\Resources;

use App\Models\WeeklyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Weekly Review as the review list shows it: its Week, its status and its summary, without the notes and advice.
 *
 * @mixin WeeklyReview
 */
class WeeklyReviewSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, week: string, status: string, summary: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week' => $this->week->toDateString(),
            'status' => $this->status->value,
            'summary' => $this->summary,
        ];
    }
}
