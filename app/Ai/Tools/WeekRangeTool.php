<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A read-only tool of the Weekly Review agent, bound to the owner, that the agent calls for a range of Weeks it chooses:
 * a day in the first and a day in the last Week.
 */
abstract class WeekRangeTool implements Tool
{
    /**
     * The most Weeks one call may read.
     */
    protected const int MAX_WEEKS = 104;

    public function __construct(protected User $user) {}

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('A day in the first Week of the range, as YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('A day in the last Week of the range, as YYYY-MM-DD. The range spans at most '.static::MAX_WEEKS.' Weeks.')->required(),
        ];
    }

    /**
     * The requested range in the owner's timezone: the moment it starts and ends (the Monday after its last Week), and its Weeks oldest first.
     * An invalid or too long range goes back to the agent to correct.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, weeks: list<CarbonImmutable>}
     *
     * @throws ValidationException
     */
    protected function range(Request $request): array
    {
        $range = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $calendar = WeekCalendar::for($this->user);
        $first = $calendar->weekOfDay($range['from']);
        $last = $calendar->weekOfDay($range['to']);

        if ((int) round($first->diffInWeeks($last)) >= static::MAX_WEEKS) {
            throw ValidationException::withMessages(['to' => 'The range can span at most '.static::MAX_WEEKS.' Weeks.']);
        }

        return ['start' => $first, 'end' => $last->addWeek(), 'weeks' => $calendar->weeksBetween($first, $last)];
    }
}
