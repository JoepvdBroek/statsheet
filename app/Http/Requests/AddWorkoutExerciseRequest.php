<?php

namespace App\Http\Requests;

use App\Models\Exercise;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddWorkoutExerciseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => [
                'required',
                'integer',
                Rule::exists('exercises', 'id')->where('user_id', $this->user()->id)->whereNull('archived_at'),
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
            'exercise_id.exists' => 'Choose one of your Exercises in use.',
        ];
    }

    /**
     * The validated Exercise to add.
     */
    public function exercise(): Exercise
    {
        return $this->user()->exercises()->findOrFail((int) $this->validated('exercise_id'));
    }
}
