<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\WeeklyTrendController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutExerciseController;
use App\Http\Controllers\WorkoutSetController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('exercises', ExerciseController::class);
    Route::post('exercises/{exercise}/restore', [ExerciseController::class, 'restore'])->name('exercises.restore');

    Route::resource('routines', RoutineController::class)->except(['show', 'destroy']);
    Route::post('routines/{routine}/archive', [RoutineController::class, 'archive'])->name('routines.archive');
    Route::post('routines/{routine}/restore', [RoutineController::class, 'restore'])->name('routines.restore');
    Route::post('routines/{routine}/start', [RoutineController::class, 'start'])->name('routines.start');

    Route::resource('goals', GoalController::class)->only(['index', 'update', 'destroy'])->parameters(['goals' => 'muscle']);

    Route::get('statistics/weekly-trend', WeeklyTrendController::class)->name('statistics.weekly-trend');

    Route::resource('workouts', WorkoutController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('workouts/{workout}/finish', [WorkoutController::class, 'finish'])->name('workouts.finish');
    Route::put('workouts/{workout}/routine', [WorkoutController::class, 'updateRoutine'])->name('workouts.routine.update');

    Route::scopeBindings()->group(function () {
        Route::post('workouts/{workout}/exercises', [WorkoutExerciseController::class, 'store'])->name('workouts.exercises.store');
        Route::put('workouts/{workout}/exercises/{exercise}/position', [WorkoutExerciseController::class, 'move'])->name('workouts.exercises.move');
        Route::delete('workouts/{workout}/exercises/{exercise}', [WorkoutExerciseController::class, 'destroy'])->name('workouts.exercises.destroy');

        Route::post('workouts/{workout}/exercises/{exercise}/sets', [WorkoutSetController::class, 'store'])->name('workouts.sets.store');
        Route::patch('workouts/{workout}/sets/{set}', [WorkoutSetController::class, 'update'])->name('workouts.sets.update');
        Route::put('workouts/{workout}/sets/{set}/done', [WorkoutSetController::class, 'markDone'])->name('workouts.sets.done');
        Route::delete('workouts/{workout}/sets/{set}/done', [WorkoutSetController::class, 'markNotDone'])->name('workouts.sets.undone');
        Route::put('workouts/{workout}/sets/{set}/position', [WorkoutSetController::class, 'move'])->name('workouts.sets.move');
        Route::delete('workouts/{workout}/sets/{set}', [WorkoutSetController::class, 'destroy'])->name('workouts.sets.destroy');
    });
});

require __DIR__.'/settings.php';
