<?php

namespace App\Http\Controllers;

use App\Enums\Muscle;
use App\Http\Requests\GoalRequest;
use App\Support\WeekCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    /**
     * List every Muscle with its Goal in force this Week, if any.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $goals = $user->goalsInForce(WeekCalendar::for($user)->currentWeek());

        return Inertia::render('goals/index', [
            'goals' => array_map(fn (Muscle $muscle) => [
                'muscle' => $muscle->value,
                'weekly_minimum' => $goals[$muscle->value] ?? null,
            ], Muscle::cases()),
        ]);
    }

    /**
     * Set or change a Muscle's Goal from this Week on.
     */
    public function update(GoalRequest $request, Muscle $muscle): RedirectResponse
    {
        $request->user()->setGoal($muscle, $request->weeklyMinimum());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal saved.')]);

        return to_route('goals.index');
    }

    /**
     * Remove a Muscle's Goal from this Week on. Earlier Weeks are still judged against it.
     */
    public function destroy(Request $request, Muscle $muscle): RedirectResponse
    {
        $request->user()->removeGoal($muscle);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Goal removed.')]);

        return to_route('goals.index');
    }
}
