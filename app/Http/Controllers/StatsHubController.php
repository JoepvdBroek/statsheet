<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeeklyReviewResource;
use App\Support\MuscleList;
use App\Support\SetCountCalculator;
use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatsHubController extends Controller
{
    /**
     * Show the Stats hub: this Week's Volume per Muscle against its Goal, its Set Count and the latest Weekly Review, with the way into every statistics view.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator, SetCountCalculator $setCountCalculator): Response
    {
        $user = $request->user();
        $week = WeekCalendar::for($user)->currentWeek();
        $goals = $user->goalsInForce($week);
        $muscles = MuscleList::of(
            $volumeCalculator->forWeek($user, $week),
            $setCountCalculator->forWeek($user, $week),
            array_keys($goals),
        );
        $latestReview = $user->weeklyReviews()->latest('week')->first();

        return Inertia::render('statistics/hub', [
            'thisWeek' => [
                'starts_on' => $week->toDateString(),
                'muscles' => $this->againstGoals($muscles, $goals),
            ],
            'latestReview' => $latestReview === null ? null : WeeklyReviewResource::make($latestReview)->resolve(),
        ]);
    }

    /**
     * The listed Muscles, each with its Goal in force and whether its Volume meets it.
     *
     * @param  list<array{muscle: string, volume: float, set_count: float, direct_sets: float}>  $muscles  As MuscleList gives them
     * @param  array<string, float>  $goals
     * @return list<array{muscle: string, volume: float, set_count: float, direct_sets: float, goal: float|null, met: bool|null}>
     */
    private function againstGoals(array $muscles, array $goals): array
    {
        return array_map(fn (array $listed) => [
            ...$listed,
            'goal' => $goals[$listed['muscle']] ?? null,
            'met' => isset($goals[$listed['muscle']]) ? $listed['volume'] >= $goals[$listed['muscle']] : null,
        ], $muscles);
    }
}
