<?php

namespace App\Rules;

use App\Support\Supersets;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A list of Exercises, in order, each with an optional `superset` number, whose Supersets each sit next to each other in the order.
 * Items or numbers of the wrong type are left to the item's own rules.
 */
class ContiguousSupersets implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $supersets = array_map(
            fn (mixed $item) => is_array($item) && is_numeric($item['superset'] ?? null) ? (int) $item['superset'] : null,
            array_values($value),
        );

        if (! Supersets::areContiguous($supersets)) {
            $fail('The Exercises of a Superset must be next to each other.');
        }
    }
}
