<?php

namespace App\Support;

/**
 * The Superset numbers of a Routine's or Workout's Exercises, in order. Exercises with the same number form one Superset and null means
 * none. Stored numbers keep the invariant: each Superset is one run of neighbouring Exercises with at least two members, numbered 1, 2, …
 * in order.
 */
class Supersets
{
    /**
     * Whether every Superset number is used by one run of neighbouring Exercises only.
     *
     * @param  list<int|null>  $supersets
     */
    public static function areContiguous(array $supersets): bool
    {
        $seen = [];
        $previous = null;

        foreach ($supersets as $superset) {
            if ($superset !== null && $superset !== $previous) {
                if (isset($seen[$superset])) {
                    return false;
                }

                $seen[$superset] = true;
            }

            $previous = $superset;
        }

        return true;
    }

    /**
     * Number the Supersets 1, 2, … in order and take a Superset with a single member out of it. Each run of neighbouring Exercises with
     * the same number counts as one Superset.
     *
     * @param  list<int|null>  $supersets
     * @return list<int|null>
     */
    public static function normalise(array $supersets): array
    {
        $runs = [];

        foreach ($supersets as $index => $superset) {
            if ($index > 0 && $superset === $supersets[$index - 1]) {
                $runs[array_key_last($runs)]['size']++;
            } else {
                $runs[] = ['superset' => $superset, 'size' => 1];
            }
        }

        $normalised = [];
        $number = 0;

        foreach ($runs as $run) {
            $isSuperset = $run['superset'] !== null && $run['size'] >= 2;
            array_push($normalised, ...array_fill(0, $run['size'], $isSuperset ? ++$number : null));
        }

        return $normalised;
    }

    /**
     * The letter a Superset is labelled by: A for the first, B for the second, and after Z on to AA, AB, ….
     */
    public static function letter(int $superset): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $letter = '';

        for ($remaining = $superset; $remaining > 0; $remaining = intdiv($remaining - 1, 26)) {
            $letter = $alphabet[($remaining - 1) % 26].$letter;
        }

        return $letter;
    }
}
