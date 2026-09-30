<?php

namespace App\Http\Requests;

use App\Enums\Equipment;
use App\Enums\Muscle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'equipment' => ['nullable', Rule::enum(Equipment::class)],
            'is_bodyweight' => ['boolean'],
            'primary_muscles' => ['required', 'array'],
            'primary_muscles.*' => ['distinct', Rule::enum(Muscle::class)],
            'secondary_muscles' => ['nullable', 'array'],
            'secondary_muscles.*' => [
                'distinct',
                Rule::enum(Muscle::class),
                Rule::notIn(array_filter((array) $this->input('primary_muscles'), 'is_string')),
            ],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_muscles.required' => 'Choose at least one primary Muscle.',
            'secondary_muscles.*.not_in' => 'A Muscle can\'t be both primary and secondary.',
        ];
    }

    /**
     * The validated Exercise attributes, without its Muscles.
     *
     * @return array{name: string, equipment: string|null, is_bodyweight: bool}
     */
    public function exerciseAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'equipment' => $this->validated('equipment'),
            'is_bodyweight' => $this->boolean('is_bodyweight'),
        ];
    }

    /**
     * @return array<int, Muscle>
     */
    public function primaryMuscles(): array
    {
        return $this->enums('primary_muscles', Muscle::class);
    }

    /**
     * @return array<int, Muscle>
     */
    public function secondaryMuscles(): array
    {
        return $this->enums('secondary_muscles', Muscle::class);
    }
}
