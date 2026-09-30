<?php

namespace App\Http\Controllers;

use App\Actions\AskForWeeklyReview;
use App\Http\Requests\WeeklyReviewRequest;
use App\Http\Resources\WeeklyReviewResource;
use App\Http\Resources\WeeklyReviewSummaryResource;
use App\Models\WeeklyReview;
use App\Support\WeekCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyReviewController extends Controller
{
    /**
     * List the owner's Weekly Reviews by Week, newest first, with the current Week to offer asking for its review.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('reviews/index', [
            'currentWeek' => WeekCalendar::for($user)->currentWeek()->toDateString(),
            'reviews' => WeeklyReviewSummaryResource::collection($user->weeklyReviews()->latest('week')->get())->resolve(),
        ]);
    }

    /**
     * Ask for a Week's Weekly Review, or a fresh one, for the current Week or any past Week.
     */
    public function store(WeeklyReviewRequest $request, AskForWeeklyReview $askForWeeklyReview): RedirectResponse
    {
        $review = $askForWeeklyReview($request->user(), $request->week());

        return to_route('reviews.show', $review);
    }

    /**
     * Show a Weekly Review, which the page polls while it is being generated.
     */
    #[Authorize('view', 'review')]
    public function show(WeeklyReview $review): Response
    {
        return Inertia::render('reviews/show', [
            'review' => WeeklyReviewResource::make($review)->resolve(),
        ]);
    }
}
