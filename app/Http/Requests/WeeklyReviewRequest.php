<?php

namespace App\Http\Requests;

use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WeeklyReviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'week' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->week()->greaterThan(WeekCalendar::for($this->user())->currentWeek())) {
                    $validator->errors()->add('week', __('This Week hasn\'t started yet.'));
                }
            },
        ];
    }

    /**
     * The Week of the given day, in the owner's timezone.
     */
    public function week(): CarbonImmutable
    {
        return WeekCalendar::for($this->user())->weekOfDay($this->input('week'));
    }
}
