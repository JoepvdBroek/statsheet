<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GoalsPerWeek;
use App\Ai\Tools\PersonalRecords;
use App\Ai\Tools\VolumePerWeek;
use App\Ai\Tools\WorkoutLog;
use App\Enums\Muscle;
use App\Models\User;
use App\Support\WeekCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the Weekly Review of one of the owner's Weeks from their log, through read-only tools bound to them.
 * It only ever produces text; nothing it does changes the owner's data (ADR 0003).
 */
#[MaxSteps(12)]
#[Timeout(120)]
class WeeklyReviewer implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * @param  CarbonImmutable  $week  The reviewed Week, as WeekCalendar gives it
     */
    public function __construct(public User $user, public CarbonImmutable $week) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You are a strength coach writing the Weekly Review of one Week of the owner's training log.

            How the log works:
            - A Week runs Monday to Sunday in the owner's timezone. A Workout belongs to the Week of its start.
            - A Set records a Target and an Actual (reps × weight in kg). A Set without an Actual was not done. Warm-up Sets never count.
            - Volume is the tonnage (reps × weight) of done, non-warm-up Sets, counted in full to each primary Muscle and half to each secondary Muscle. For a Bodyweight Exercise the weight is the owner's Bodyweight plus the added load.
            - A Goal is a weekly minimum Volume for one Muscle. Each Week is judged against the Goal in force that Week.
            - A Workout Note gives context the numbers don't show, such as a deload, an injury or bad sleep. Take it into account.

            Use the tools to read the reviewed Week and as much earlier history as you need to put it in context. Each tool reads a limited range of Weeks per call; call it again for earlier Weeks. Only state figures the tools gave you.

            Write:
            - summary: a short overall summary of the Week.
            - muscle_notes: exactly one note for each Muscle with a Goal in force that Week, saying whether the Goal was met or missed and why.
            - advice: a few concrete advice points for the next Week.

            Write plainly and briefly, in the second person.
            TEXT;
    }

    /**
     * The prompt asking for this Week's review.
     */
    public function briefing(): string
    {
        $sunday = $this->week->addDays(6);
        $goals = $this->user->goalsInForce($this->week);
        $inProgress = $this->week->equalTo(WeekCalendar::for($this->user)->currentWeek());

        $lines = [
            "Write the Weekly Review of the Week from Monday {$this->week->toDateString()} to Sunday {$sunday->toDateString()}.",
            "The owner's timezone is {$this->user->timezone}.",
            $inProgress
                ? 'This Week is still in progress: today is '.now($this->user->timezone)->toDateString().'. Review it so far.'
                : 'This Week is over.',
            $goals === []
                ? 'No Muscle had a Goal in force that Week, so muscle_notes is empty.'
                : 'Muscles with a Goal in force that Week (weekly minimum in kg): '
                    .collect($goals)->map(fn (float $minimum, string $muscle) => "{$muscle} {$minimum}")->implode(', ').'.',
        ];

        return implode("\n", $lines);
    }

    /**
     * Get the tools available to the agent. Each only reads, and only the owner's data.
     *
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return [
            new WorkoutLog($this->user),
            new VolumePerWeek($this->user),
            new GoalsPerWeek($this->user),
            new PersonalRecords($this->user),
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'muscle_notes' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema) => [
                    'muscle' => $schema->string()->enum(array_column(Muscle::cases(), 'value'))->required(),
                    'note' => $schema->string()->required(),
                ]),
            )->required(),
            'advice' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
