<?php

namespace App\Http\Requests;

use App\Enums\ReviewRating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RateWeeklyReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', Rule::enum(ReviewRating::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The thumbs up or down.
     */
    public function rating(): ReviewRating
    {
        return ReviewRating::from($this->validated('rating'));
    }

    /**
     * The comment, if any was written.
     */
    public function comment(): ?string
    {
        return $this->validated('comment');
    }
}
