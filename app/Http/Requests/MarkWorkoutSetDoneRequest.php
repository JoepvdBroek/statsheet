<?php

namespace App\Http\Requests;

use App\Models\WorkoutSet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class MarkWorkoutSetDoneRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Both values are optional: what isn't typed comes from the Set's Target.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_reps' => ['nullable', 'integer', 'min:1', 'max:999'],
            'actual_weight' => ['nullable', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
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
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $actual = $this->resolvedActual();

                if ($actual['reps'] === null) {
                    $validator->errors()->add('actual_reps', 'Enter the reps you did.');
                }

                if ($actual['weight'] === null) {
                    $validator->errors()->add('actual_weight', 'Enter the weight you lifted.');
                }
            },
        ];
    }

    /**
     * The validated Actual to log, with what wasn't typed taken from the Target.
     *
     * @return array{reps: int, weight: string}
     */
    public function actual(): array
    {
        $actual = $this->resolvedActual();

        return [
            'reps' => (int) $actual['reps'],
            'weight' => number_format((float) $actual['weight'], 2, '.', ''),
        ];
    }

    /**
     * @return array{reps: int|null, weight: string|null}
     */
    private function resolvedActual(): array
    {
        $typedReps = $this->input('actual_reps');
        $typedWeight = $this->input('actual_weight');

        return $this->workoutSet()->actualWhenMarkedDone(
            $typedReps === null ? null : (int) $typedReps,
            $typedWeight === null ? null : (string) $typedWeight,
        );
    }

    private function workoutSet(): WorkoutSet
    {
        $set = $this->route('set');

        if (! $set instanceof WorkoutSet) {
            throw new LogicException('Marking a Set done needs the route to bind a Workout Set.');
        }

        return $set;
    }
}
