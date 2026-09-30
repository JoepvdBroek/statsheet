<?php

namespace App\Http\Controllers;

use App\Enums\Equipment;
use App\Enums\Muscle;
use App\Http\Requests\ExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Support\PersonalRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    /**
     * List the owner's Exercises, searched by name and filtered by Muscle and equipment.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString() ?: null;
        $muscle = $request->enum('muscle', Muscle::class);
        $equipment = $request->enum('equipment', Equipment::class);
        $archived = $request->boolean('archived');

        $exercises = $request->user()->exercises()
            ->with('muscles')
            ->when($archived, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->when($search, fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->when($muscle, fn ($query) => $query->trains($muscle))
            ->when($equipment, fn ($query) => $query->where('equipment', $equipment))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('exercises/index', [
            'exercises' => Inertia::scroll(ExerciseResource::collection($exercises)),
            'filters' => [
                'search' => $search,
                'muscle' => $muscle?->value,
                'equipment' => $equipment?->value,
                'archived' => $archived,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Show the form for creating an Exercise.
     */
    public function create(): Response
    {
        return Inertia::render('exercises/create', $this->formOptions());
    }

    /**
     * Create an Exercise for the owner.
     */
    public function store(ExerciseRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $exercise = $request->user()->exercises()->create($request->exerciseAttributes());

            $exercise->syncMuscles($request->primaryMuscles(), $request->secondaryMuscles());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exercise created.')]);

        return to_route('exercises.index');
    }

    /**
     * Show an Exercise's progress: its Personal Records, its best Estimated 1RM and heaviest weight per Workout over time, and its recent performances.
     */
    #[Authorize('view', 'exercise')]
    public function show(Exercise $exercise, PersonalRecords $personalRecords): Response
    {
        return Inertia::render('exercises/show', [
            'exercise' => ExerciseResource::make($exercise->load('muscles'))->resolve(),
            'records' => $personalRecords->of($exercise),
            'progress' => $personalRecords->perWorkout($exercise),
            'recent' => $personalRecords->recentPerformances($exercise),
        ]);
    }

    /**
     * Show the form for editing an Exercise.
     */
    #[Authorize('update', 'exercise')]
    public function edit(Exercise $exercise): Response
    {
        return Inertia::render('exercises/edit', [
            'exercise' => ExerciseResource::make($exercise->load('muscles'))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Save changes to an Exercise, replacing its Muscles.
     */
    #[Authorize('update', 'exercise')]
    public function update(ExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        DB::transaction(function () use ($request, $exercise) {
            $exercise->update($request->exerciseAttributes());

            $exercise->syncMuscles($request->primaryMuscles(), $request->secondaryMuscles());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exercise saved.')]);

        return to_route('exercises.index');
    }

    /**
     * Delete an Exercise, or archive it when a Routine or Workout uses it.
     */
    #[Authorize('delete', 'exercise')]
    public function destroy(Exercise $exercise): RedirectResponse
    {
        $archived = $exercise->retire();

        Inertia::flash('toast', ['type' => 'success', 'message' => $archived
            ? __('Exercise archived, because a Routine or Workout uses it.')
            : __('Exercise deleted.'),
        ]);

        return to_route('exercises.index');
    }

    /**
     * Put an archived Exercise back in use.
     */
    #[Authorize('restore', 'exercise')]
    public function restore(Exercise $exercise): RedirectResponse
    {
        $exercise->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exercise restored.')]);

        return to_route('exercises.index', ['archived' => 1]);
    }

    /**
     * The choices an Exercise form offers.
     *
     * @return array{muscles: array<int, string>, equipment: array<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'muscles' => array_column(Muscle::cases(), 'value'),
            'equipment' => array_column(Equipment::cases(), 'value'),
        ];
    }
}
