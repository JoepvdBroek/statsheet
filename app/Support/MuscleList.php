<?php

namespace App\Support;

/**
 * Brings Volume and Set Count together per Muscle, for the pages that list them side by side.
 *
 * @phpstan-import-type SetCount from SetCountCalculator
 */
class MuscleList
{
    /**
     * Each Muscle with Volume or a Set Count, and any others given, with 0 for what it lacks.
     * Most Volume first, then Muscles without Volume by name.
     *
     * @param  array<string, float>  $volume  Volume per Muscle, most Volume first, as VolumeCalculator gives it
     * @param  array<string, SetCount>  $setCounts  Set Count per Muscle, as SetCountCalculator gives it
     * @param  list<string>  $others  Muscles to list even without Volume or a Set Count
     * @return list<array{muscle: string, volume: float, set_count: float, direct_sets: float}>
     */
    public static function of(array $volume, array $setCounts, array $others = []): array
    {
        $withVolume = array_keys($volume);
        $withoutVolume = array_unique(array_diff([...array_keys($setCounts), ...$others], $withVolume));
        sort($withoutVolume);

        return array_map(fn (string $muscle) => [
            'muscle' => $muscle,
            'volume' => $volume[$muscle] ?? 0.0,
            'set_count' => $setCounts[$muscle]['set_count'] ?? 0.0,
            'direct_sets' => $setCounts[$muscle]['direct_sets'] ?? 0.0,
        ], [...$withVolume, ...$withoutVolume]);
    }
}
