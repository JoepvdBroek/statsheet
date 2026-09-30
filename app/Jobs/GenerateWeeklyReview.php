<?php

namespace App\Jobs;

use App\Ai\Agents\WeeklyReviewer;
use App\Models\WeeklyReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

/**
 * Generates one Weekly Review with the AI agent and stores it, replacing the earlier content only once the new text is in.
 * A failure keeps the earlier content and marks the review failed, so the owner can retry.
 */
class GenerateWeeklyReview implements ShouldQueue
{
    use Queueable;

    /**
     * Retrying is up to the owner, so each attempt costs at most one agent run.
     */
    public int $tries = 1;

    /**
     * The agent may call its tools several times, each call a request to the provider.
     */
    public int $timeout = 240;

    public function __construct(public WeeklyReview $review) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->review->user;
        $week = $this->review->reviewedWeek();

        try {
            $agent = new WeeklyReviewer($user, $week);
            $response = $agent->prompt($agent->briefing());

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The Weekly Review agent answered without structured output.');
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->review->markFailed();

            return;
        }

        $this->review->storeGeneration([
            'summary' => $response['summary'],
            'muscle_notes' => $this->notesOnGoals($response['muscle_notes'], $user->goalsInForce($week)),
            'advice' => $response['advice'],
        ], $response->meta->model);
    }

    /**
     * The first note for each Muscle with a Goal in force that Week, leaving out any other the agent wrote.
     *
     * @param  list<array{muscle: string, note: string}>  $notes
     * @param  array<string, float>  $goals
     * @return list<array{muscle: string, note: string}>
     */
    private function notesOnGoals(array $notes, array $goals): array
    {
        $kept = [];

        foreach ($notes as $note) {
            if (isset($goals[$note['muscle']]) && ! isset($kept[$note['muscle']])) {
                $kept[$note['muscle']] = $note;
            }
        }

        return array_values($kept);
    }

    /**
     * Mark the review failed when the queue gives up on the job, such as after a timeout.
     */
    public function failed(?Throwable $exception): void
    {
        $this->review->markFailed();
    }
}
