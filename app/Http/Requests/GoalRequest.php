<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GoalRequest extends FormRequest
{
    /**
     * Read a decimal comma, as typed on a Dutch keypad, as a decimal point.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('weekly_minimum'))) {
            $this->merge(['weekly_minimum' => str_replace(',', '.', $this->input('weekly_minimum'))]);
        }
    }

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
