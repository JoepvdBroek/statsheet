<?php

namespace App\Enums;

/**
 * How an Exercise trains a Muscle: primary Muscles get full Volume, secondary Muscles half.
 */
enum MuscleRole: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';
}
