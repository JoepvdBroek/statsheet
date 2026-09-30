<?php

namespace App\Http\Controllers;

use App\Actions\AskForWeeklyReview;
use App\Http\Requests\WeeklyReviewRequest;
use App\Http\Resources\WeeklyReviewResource;
use App\Models\WeeklyReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyReviewController extends Controller
{
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
