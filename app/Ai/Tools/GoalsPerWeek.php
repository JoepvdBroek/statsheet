<?php

namespace App\Ai\Tools;

use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's Goals in force in each Week of a range. It only reads.
 */
class GoalsPerWeek extends WeekRangeTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'The Goals in force in each Week of a range of at most '.static::MAX_WEEKS.' Weeks: the weekly minimum Volume in kg per Muscle, keyed by the Week\'s Monday and then by Muscle. A Week without Goals is empty.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->user->goalsInForcePerWeek($this->range($request)['weeks']), JSON_THROW_ON_ERROR);
    }
}
