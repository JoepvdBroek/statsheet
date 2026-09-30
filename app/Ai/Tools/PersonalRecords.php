<?php

namespace App\Ai\Tools;

use App\Models\Exercise;
use App\Support\ExerciseProgress;
use App\Support\WeekCalendar;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's Personal Records per Exercise as they stood at the end of a range, and the Weeks of the range in which they were beaten. It only reads.
 */
class PersonalRecords extends WeekRangeTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'The Personal Records of each performed Exercise as they stood at the end of a range of at most '.static::MAX_WEEKS.' Weeks: heaviest weight, best Estimated 1RM, most reps at each weight and best set tonnage, all in kg '
            .'(for a Bodyweight Exercise, heaviest and reps-at-weight use the added load; the others add the Bodyweight). '
            .'beaten_per_week lists, per Week of the range in which any were beaten, the records beaten, keyed by the Week\'s Monday.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        ['start' => $start, 'end' => $end] = $this->range($request);
        $calendar = WeekCalendar::for($this->user);

        $exercises = $this->user->exercises()->whereHas('workoutExercises')->orderBy('name')->orderBy('id')->get()
            ->map(function (Exercise $exercise) use ($start, $end, $calendar) {
                $progress = ExerciseProgress::of($exercise, before: $end);
                $records = $progress->records();

                if ($records['heaviest'] === null) {
                    return null;
                }

                $beatenPerWeek = [];

                foreach ($progress->recordsBeaten() as ['started_at' => $startedAt, 'beaten' => $beaten]) {
                    if ($startedAt->greaterThanOrEqualTo($start)) {
                        $week = $calendar->weekOf($startedAt)->toDateString();
                        $beatenPerWeek[$week] = array_values(array_unique([...$beatenPerWeek[$week] ?? [], ...$beaten]));
                    }
                }

                return [
                    'exercise' => $exercise->name,
                    'bodyweight_exercise' => $exercise->is_bodyweight,
                    'records' => $records,
                    'beaten_per_week' => $beatenPerWeek,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return json_encode($exercises, JSON_THROW_ON_ERROR);
    }
}
