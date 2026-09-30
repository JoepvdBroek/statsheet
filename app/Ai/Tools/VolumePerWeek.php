<?php

namespace App\Ai\Tools;

use App\Support\VolumeCalculator;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's Volume per Muscle in each Week of a range. It only reads.
 */
class VolumePerWeek extends WeekRangeTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Volume in kg per Muscle in each Week of a range of at most '.static::MAX_WEEKS.' Weeks, keyed by the Week\'s Monday and then by Muscle, most Volume first. A Week without Volume is empty.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return json_encode((new VolumeCalculator)->perWeek($this->user, $this->range($request)['weeks']), JSON_THROW_ON_ERROR);
    }
}
