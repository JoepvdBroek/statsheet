<?php

namespace App\Http\Controllers;

use App\Http\Resources\WeeklyReviewResource;
use App\Support\VolumeCalculator;
use App\Support\WeekCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatsHubController extends Controller
{
    /**
     * Show the Stats hub: this Week's Volume per Muscle against its Goal and the latest Weekly Review, with the way into every statistics view.
     */
    public function __invoke(Request $request, VolumeCalculator $volumeCalculator): Response
    {
        $user = $request->user();
        $week = WeekCalendar::for($user)->currentWeek();
        $volume = $volumeCalculator->forWeek($user, $week);
        $latestReview = $user->weeklyReviews()->latest('week')->first();

        return Inertia::render('statistics/hub', [
            'thisWeek' => [
                'starts_on' => $week->toDateString(),
                'muscles' => $this->againstGoals($volume, $user->goalsInForce($week)),
            ],
            'latestReview' => $latestReview === null ? null : WeeklyReviewResource::make($latestReview)->resolve(),
        ]);
    }

    /**
     * Each Muscle with Volume or a Goal in force: its Volume, its Goal, and whether the Goal is met. Most Volume first, then Muscles with a Goal but no Volume by name.
     *
     * @param  array<string, float>  $volume
     * @param  array<string, float>  $goals
     * @return list<array{muscle: string, volume: float, goal: float|null, met: bool|null}>
     */
    private function againstGoals(array $volume, array $goals): array
    {
        $goalsWithoutVolume = array_map(fn () => 0.0, array_diff_key($goals, $volume));
        ksort($goalsWithoutVolume);
        $muscles = $volume + $goalsWithoutVolume;

        return array_map(fn (string $muscle) => [
            'muscle' => $muscle,
            'volume' => $muscles[$muscle],
            'goal' => $goals[$muscle] ?? null,
            'met' => isset($goals[$muscle]) ? $muscles[$muscle] >= $goals[$muscle] : null,
        ], array_keys($muscles));
    }
}
