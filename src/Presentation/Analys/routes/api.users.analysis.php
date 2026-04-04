<?php

declare(strict_types=1);

use Presentation\Analys\Controllers\UserAnalysController;
use Presentation\Analys\Controllers\UserAnalysSearchController;

Route::prefix('users/{userId}/analysis')->middleware(['auth'])->group(function () {
    Route::post('', [UserAnalysController::class, 'create'])->name('api.users.analysis.create');
    Route::get('', [UserAnalysController::class, 'index'])->name('api.users.analysis.index');
    Route::delete('', [UserAnalysController::class, 'destroy'])->name('api.users.analysis.destroy');
    Route::get('search', UserAnalysSearchController::class)->name('api.users.analysis.search');
});
