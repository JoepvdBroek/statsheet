<?php

namespace App\Enums;

/**
 * How an Exercise trains a Muscle: primary Muscles get full Volume and Set Count, secondary Muscles half.
 */
enum MuscleRole: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    /**
     * The share of a Set's Volume and Set Count a Muscle in this role gets.
     */
    public function share(): float
    {
        return match ($this) {
            self::Primary => 1.0,
            self::Secondary => 0.5,
        };
    }
}
