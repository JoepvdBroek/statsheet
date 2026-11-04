<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Read a decimal comma in the Bodyweight, as typed on a Dutch keypad, as a decimal point.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('bodyweight'))) {
            $this->merge(['bodyweight' => str_replace(',', '.', $this->input('bodyweight'))]);
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
            ...$this->profileRules($this->user()->id),
            'bodyweight' => $this->bodyweightRules(),
            'timezone' => $this->timezoneRules(),
        ];
    }
}
