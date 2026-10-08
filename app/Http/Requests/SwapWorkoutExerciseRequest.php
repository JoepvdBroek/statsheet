<?php

namespace App\Http\Requests;

use App\Models\WorkoutExercise;
use Illuminate\Validation\Validator;

/**
 * Swap one of the owner's Exercises in use into a Workout, which is only possible before any of the swapped-out Sets is done.
 */
class SwapWorkoutExerciseRequest extends AddWorkoutExerciseRequest
{
    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var WorkoutExercise $swapped */
                $swapped = $this->route('exercise');

                if ($swapped->hasDoneSet()) {
                    $validator->errors()->add('exercise_id', 'Swap an Exercise only before any of its Sets is done.');
                }
            },
        ];
    }
}
