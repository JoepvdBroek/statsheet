<?php

namespace App\Http\Requests;

use App\Enums\SetKind;
use App\Models\Routine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoutineRequest extends FormRequest
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
            'exercises' => ['array'],
            'exercises.*' => ['array:exercise_id,sets'],
            'exercises.*.exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')->where('user_id', $this->user()->id)],
            'exercises.*.sets' => ['required', 'array', 'min:1'],
            'exercises.*.sets.*' => ['array:target_reps,target_weight,kind'],
            'exercises.*.sets.*.target_reps' => ['required', 'integer', 'min:1', 'max:999'],
            'exercises.*.sets.*.target_weight' => ['required', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'exercises.*.sets.*.kind' => [Rule::enum(SetKind::class)],
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
            'exercises.*.exercise_id.exists' => 'Choose one of your Exercises.',
            'exercises.*.sets.required' => 'Give each Exercise at least one Set.',
            'exercises.*.sets.min' => 'Give each Exercise at least one Set.',
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
            'exercises.*.sets.*.target_reps' => 'target reps',
            'exercises.*.sets.*.target_weight' => 'target weight',
            'exercises.*.sets.*.kind' => 'kind',
        ];
    }

    /**
     * The validated plan: Exercises in order, each with its Sets in order.
     *
     * @return list<array{exercise_id: int, sets: list<array{target_reps: int, target_weight: string, kind: SetKind}>}>
     */
    public function plannedExercises(): array
    {
        return array_map(fn (array $planned) => [
            'exercise_id' => (int) $planned['exercise_id'],
            'sets' => array_map(fn (array $set) => [
                'target_reps' => (int) $set['target_reps'],
                'target_weight' => number_format((float) $set['target_weight'], 2, '.', ''),
                'kind' => SetKind::tryFrom($set['kind'] ?? '') ?? SetKind::Working,
            ], array_values($planned['sets'])),
        ], array_values($this->validated('exercises', [])));
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
                $this->rejectAddedArchivedExercises($validator);
            },
        ];
    }

    /**
     * An archived Exercise may stay where the Routine already plans it, but can't be planned any more times than that.
     */
    private function rejectAddedArchivedExercises(Validator $validator): void
    {
        $plannedIds = collect((array) $this->input('exercises'))
            ->map(fn (mixed $planned) => is_array($planned) && is_numeric($planned['exercise_id'] ?? null) ? (int) $planned['exercise_id'] : null)
            ->filter();
        $archivedIds = $this->user()->exercises()->archived()->whereKey($plannedIds->all())->pluck('id')->all();

        if ($archivedIds === []) {
            return;
        }

        $routine = $this->route('routine');
        $allowed = $routine instanceof Routine
            ? array_count_values($routine->exercises()->whereIn('exercise_id', $archivedIds)->pluck('exercise_id')->all())
            : [];

        foreach ($plannedIds as $index => $exerciseId) {
            if (! in_array($exerciseId, $archivedIds, true)) {
                continue;
            }

            if (($allowed[$exerciseId] ?? 0) > 0) {
                $allowed[$exerciseId]--;

                continue;
            }

            $validator->errors()->add("exercises.{$index}.exercise_id", 'Archived Exercises can\'t be added to a Routine.');
        }
    }
}
