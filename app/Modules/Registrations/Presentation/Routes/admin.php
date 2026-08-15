<?php

declare(strict_types=1);

use App\Modules\Registrations\Presentation\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('tournaments/{tournament}/categories/{category:slug}')
    ->name('tournaments.categories.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('registrations', [RegistrationController::class, 'index'])
            ->name('registrations.index');

        Route::post('registrations', [RegistrationController::class, 'store'])
            ->name('registrations.store');

        Route::put('registrations/{registration}', [RegistrationController::class, 'update'])
            ->name('registrations.update');
    });
