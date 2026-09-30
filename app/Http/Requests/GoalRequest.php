<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GoalRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weekly_minimum' => ['required', 'numeric', 'gt:0', 'max:9999999.99', 'decimal:0,2'],
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
            'weekly_minimum' => 'weekly minimum',
        ];
    }

    /**
     * The weekly minimum Volume in kg, as stored.
     */
    public function weeklyMinimum(): string
    {
        return number_format((float) $this->validated('weekly_minimum'), 2, '.', '');
    }
}
