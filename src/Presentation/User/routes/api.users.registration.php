<?php

declare(strict_types=1);

use Presentation\User\Controllers\RegistrationController;

Route::post('/register', [RegistrationController::class, 'register'])
    ->middleware(['throttle:10,1'])
    ->name('api.users.register');

Route::get('/email/verify/{id}/{hash}', [RegistrationController::class, 'verify'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/email/verification-notification', [RegistrationController::class, 'sendEmailVerification'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');
