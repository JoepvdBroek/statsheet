<?php

namespace App\Enums;

/**
 * The owner's thumbs up or down on a Weekly Review, to judge whether the reviews are any good.
 */
enum ReviewRating: string
{
    case Up = 'up';
    case Down = 'down';
}
