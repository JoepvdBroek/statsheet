<?php

namespace App\Enums;

/**
 * The 17 fixed Muscles an Exercise trains, taken unchanged from free-exercise-db (ADR 0002).
 */
enum Muscle: string
{
    case Abdominals = 'abdominals';
    case Abductors = 'abductors';
    case Adductors = 'adductors';
    case Biceps = 'biceps';
    case Calves = 'calves';
    case Chest = 'chest';
    case Forearms = 'forearms';
    case Glutes = 'glutes';
    case Hamstrings = 'hamstrings';
    case Lats = 'lats';
    case LowerBack = 'lower back';
    case MiddleBack = 'middle back';
    case Neck = 'neck';
    case Quadriceps = 'quadriceps';
    case Shoulders = 'shoulders';
    case Traps = 'traps';
    case Triceps = 'triceps';
}
