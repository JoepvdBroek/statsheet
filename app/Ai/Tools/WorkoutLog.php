<?php

namespace App\Ai\Tools;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The owner's Workouts in each Week of a range, with their Exercises, Sets and Notes. It only reads.
 */
class WorkoutLog extends WeekRangeTool
{
    /**
     * A Week of Workouts with their Sets is a lot to read, so a call covers fewer Weeks than the aggregates.
     */
    protected const int MAX_WEEKS = 12;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'The Workouts in each Week of a range of at most '.static::MAX_WEEKS.' Weeks, keyed by the Week\'s Monday, oldest first: '
            .'start time in the owner\'s timezone, whether still in progress, the Routine it was started from, the Bodyweight in kg, the Workout Note, '
            .'and each Exercise with its Sets in order. A Set has a Target and an Actual in reps and kg (for a Bodyweight Exercise the added load); '
            .'a Set without an Actual was not done. A Set\'s kind is working, warm_up or drop: '
            .'a Warm-up Set never counts, and a Drop Set was done straight after the Set before it at a lower weight, without rest, and counts like a working Set.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        ['start' => $start, 'end' => $end, 'weeks' => $weeks] = $this->range($request);
        $calendar = WeekCalendar::for($this->user);
        $log = collect($weeks)->mapWithKeys(fn (CarbonImmutable $week) => [$week->toDateString() => []])->all();

        $workouts = $this->user->workouts()
            ->where('started_at', '>=', $start->utc())
            ->where('started_at', '<', $end->utc())
            ->with('routine', 'exercises.exercise', 'exercises.sets')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        foreach ($workouts as $workout) {
            $log[$calendar->weekOf($workout->started_at)->toDateString()][] = $this->describe($workout);
        }

        return json_encode($log, JSON_THROW_ON_ERROR);
    }

    /**
     * A Workout as the agent reads it.
     *
     * @return array<string, mixed>
     */
    private function describe(Workout $workout): array
    {
        return [
            'started_at' => $workout->started_at->setTimezone($this->user->timezone)->format('Y-m-d H:i'),
            'in_progress' => $workout->isInProgress(),
            'routine' => $workout->routine?->name,
            'bodyweight' => $workout->bodyweight === null ? null : (float) $workout->bodyweight,
            'note' => $workout->note,
            'exercises' => $workout->exercises->map(fn (WorkoutExercise $performed) => [
                'exercise' => $performed->exercise->name,
                'bodyweight_exercise' => $performed->exercise->is_bodyweight,
                'sets' => $performed->sets->map(fn (WorkoutSet $set) => [
                    'target_reps' => $set->target_reps,
                    'target_weight' => $set->target_weight === null ? null : (float) $set->target_weight,
                    'actual_reps' => $set->actual_reps,
                    'actual_weight' => $set->actual_weight === null ? null : (float) $set->actual_weight,
                    'kind' => $set->kind->value,
                ])->all(),
            ])->all(),
        ];
    }
}
