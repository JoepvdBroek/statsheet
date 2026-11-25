<?php

namespace App\Http\Requests;

use App\Models\Workout;
use App\Rules\ContiguousSupersets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ArrangeWorkoutExercisesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercises' => ['required', 'array', new ContiguousSupersets],
            'exercises.*' => ['array:id,superset'],
            'exercises.*.id' => ['required', 'integer', 'distinct'],
            'exercises.*.superset' => ['nullable', 'integer', 'min:1'],
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
            'exercises.*.id' => 'Exercise',
            'exercises.*.superset' => 'superset',
        ];
    }

    /**
     * The validated arrangement: the Workout's Exercises in their new order, each with its Superset number (null when in none).
     *
     * @return list<array{id: int, superset: int|null}>
     */
    public function arrangement(): array
    {
        return array_map(fn (array $arranged) => [
            'id' => (int) $arranged['id'],
            'superset' => isset($arranged['superset']) ? (int) $arranged['superset'] : null,
        ], array_values($this->validated('exercises')));
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->rejectOtherExercises($validator);
            },
        ];
    }

    /**
     * The arrangement must list every Exercise of the Workout and no other.
     */
    private function rejectOtherExercises(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var Workout $workout */
        $workout = $this->route('workout');
        $arrangedIds = collect((array) $this->input('exercises'))->map(fn (array $arranged) => (int) $arranged['id'])->sort()->values()->all();
        $workoutIds = $workout->exercises()->pluck('id')->sort()->values()->all();

        if ($arrangedIds !== $workoutIds) {
            $validator->errors()->add('exercises', 'Arrange every Exercise of the Workout, and only those.');
        }
    }
}
