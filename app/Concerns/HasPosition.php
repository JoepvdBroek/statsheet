<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Collection;

/**
 * A model kept in order among its siblings by a 0-based `position` column.
 */
trait HasPosition
{
    /**
     * Move one of the ordered siblings to the given place and number them all again from 0.
     *
     * @param  Collection<int, self>  $siblings  All siblings, the moved one included, in their current order
     */
    public static function reposition(Collection $siblings, self $moved, int $position): void
    {
        $ordered = $siblings->reject(fn (self $sibling) => $sibling->is($moved))->values()->all();

        array_splice($ordered, max(0, min($position, count($ordered))), 0, [$moved]);

        foreach ($ordered as $index => $sibling) {
            $sibling->position = $index;
            $sibling->save();
        }
    }
}
