<?php

use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\RoutineController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('exercises', ExerciseController::class)->except(['show']);
    Route::post('exercises/{exercise}/restore', [ExerciseController::class, 'restore'])->name('exercises.restore');

    Route::resource('routines', RoutineController::class)->except(['show', 'destroy']);
    Route::post('routines/{routine}/archive', [RoutineController::class, 'archive'])->name('routines.archive');
    Route::post('routines/{routine}/restore', [RoutineController::class, 'restore'])->name('routines.restore');
});

require __DIR__.'/settings.php';
