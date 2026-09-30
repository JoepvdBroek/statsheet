<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineRequest;
use App\Http\Resources\ExerciseResource;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\DeferProp;
use Inertia\Inertia;
use Inertia\Response;

class RoutineController extends Controller
{
    /**
     * List the owner's Routines in use, or the archived ones when asked.
     */
    public function index(Request $request): Response
    {
        $archived = $request->boolean('archived');

        $routines = $request->user()->routines()
            ->with('exercises.exercise.muscles', 'exercises.sets')
            ->when($archived, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('routines/index', [
            'routines' => RoutineResource::collection($routines)->resolve(),
            'archived' => $archived,
        ]);
    }

    /**
     * Show the form for creating a Routine.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('routines/create', [
            'exercises' => $this->plannableExercises($request),
        ]);
    }

    /**
     * Create a Routine for the owner with its planned Exercises and Sets.
     */
    public function store(RoutineRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $routine = $request->user()->routines()->create(['name' => $request->validated('name')]);

            $routine->syncExercises($request->plannedExercises());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routine created.')]);

        return to_route('routines.index');
    }

    /**
     * Show the editor for a Routine's name, Exercises and Sets.
     */
    #[Authorize('update', 'routine')]
    public function edit(Request $request, Routine $routine): Response
    {
        return Inertia::render('routines/edit', [
            'routine' => RoutineResource::make($routine->load('exercises.exercise.muscles', 'exercises.sets'))->resolve(),
            'exercises' => $this->plannableExercises($request),
        ]);
    }

    /**
     * Save a Routine's name and replace its planned Exercises and Sets.
     */
    #[Authorize('update', 'routine')]
    public function update(RoutineRequest $request, Routine $routine): RedirectResponse
    {
        DB::transaction(function () use ($request, $routine) {
            $routine->update(['name' => $request->validated('name')]);

            $routine->syncExercises($request->plannedExercises());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routine saved.')]);

        return to_route('routines.index');
    }

    /**
     * Take a Routine out of use. Routines are archived, never deleted.
     */
    #[Authorize('archive', 'routine')]
    public function archive(Routine $routine): RedirectResponse
    {
        $routine->archive();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routine archived.')]);

        return to_route('routines.index');
    }

    /**
     * Put an archived Routine back in use.
     */
    #[Authorize('restore', 'routine')]
    public function restore(Routine $routine): RedirectResponse
    {
        $routine->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routine restored.')]);

        return to_route('routines.index', ['archived' => 1]);
    }

    /**
     * The owner's Exercises in use, which the editor's picker offers, loaded after the page.
     */
    private function plannableExercises(Request $request): DeferProp
    {
        return Inertia::defer(fn () => ExerciseResource::collection(
            $request->user()->exercises()->active()->with('muscles')->orderBy('name')->orderBy('id')->get(),
        )->resolve());
    }
}
