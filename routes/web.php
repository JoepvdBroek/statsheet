<?php

use App\Http\Controllers\ExerciseController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('exercises', ExerciseController::class)->except(['show']);
    Route::post('exercises/{exercise}/restore', [ExerciseController::class, 'restore'])->name('exercises.restore');
});

require __DIR__.'/settings.php';
