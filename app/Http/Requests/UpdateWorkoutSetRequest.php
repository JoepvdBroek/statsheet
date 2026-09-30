<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkoutSetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * An Actual is reps and weight together, so correcting one means sending both. Clearing it is un-marking the Set.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_reps' => ['required_with:actual_weight', 'integer', 'min:1', 'max:999'],
            'actual_weight' => ['required_with:actual_reps', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'is_warm_up' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actual_reps' => 'reps',
            'actual_weight' => 'weight',
        ];
    }

    /**
     * The corrected Actual, when one was sent.
     *
     * @return array{reps: int, weight: string}|null
     */
    public function actual(): ?array
    {
        if (! $this->has('actual_reps')) {
            return null;
        }

        return [
            'reps' => (int) $this->validated('actual_reps'),
            'weight' => number_format((float) $this->validated('actual_weight'), 2, '.', ''),
        ];
    }
}
