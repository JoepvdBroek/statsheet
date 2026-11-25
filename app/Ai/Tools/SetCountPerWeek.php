<?php

namespace App\Ai\Tools;

use App\Support\SetCountCalculator;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's Set Count and Direct Sets per Muscle in each Week of a range. It only reads.
 */
class SetCountPerWeek extends WeekRangeTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Set Count (set_count) and Direct Sets (direct_sets) per Muscle in each Week of a range of at most '.static::MAX_WEEKS.' Weeks, keyed by the Week\'s Monday and then by Muscle, highest Set Count first. A Week without Set Count is empty.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return json_encode((new SetCountCalculator)->perWeek($this->user, $this->range($request)['weeks']), JSON_THROW_ON_ERROR);
    }
}
