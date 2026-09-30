<?php

namespace App\Enums;

/**
 * The single equipment value an Exercise can have, taken unchanged from free-exercise-db.
 */
enum Equipment: string
{
    case Bands = 'bands';
    case Barbell = 'barbell';
    case BodyOnly = 'body only';
    case Cable = 'cable';
    case Dumbbell = 'dumbbell';
    case EzCurlBar = 'e-z curl bar';
    case ExerciseBall = 'exercise ball';
    case FoamRoll = 'foam roll';
    case Kettlebells = 'kettlebells';
    case Machine = 'machine';
    case MedicineBall = 'medicine ball';
    case Other = 'other';
}
