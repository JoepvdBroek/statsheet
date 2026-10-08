<?php

namespace App\Enums;

/**
 * What a Set is: a Working Set, a Warm-up Set or a Drop Set. A Set has exactly one kind.
 */
enum SetKind: string
{
    case Working = 'working';
    case WarmUp = 'warm_up';
    case Drop = 'drop';

    /**
     * Whether a Set of this kind counts toward Volume, Goals and Personal Records: every kind but Warm-up.
     */
    public function counts(): bool
    {
        return $this !== self::WarmUp;
    }
}
