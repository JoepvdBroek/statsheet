<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard: resume the Workout in progress, or start one.
     */
    public function __invoke(Request $request): Response
    {
        $workout = $request->user()->workouts()->inProgress()->first();

        return Inertia::render('dashboard', [
            'workoutInProgress' => $workout === null ? null : [
                'id' => $workout->id,
                'started_at' => $workout->started_at->toIso8601String(),
            ],
        ]);
    }
}
