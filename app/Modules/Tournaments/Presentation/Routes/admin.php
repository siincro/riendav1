<?php

declare(strict_types=1);

use App\Modules\Tournaments\Presentation\Http\Controllers\TournamentCategoryController;
use App\Modules\Tournaments\Presentation\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::resource('tournaments', TournamentController::class)
    ->parameters(['tournaments' => 'tournament']);

Route::put('tournaments/{tournament}/status', [TournamentController::class, 'changeStatus'])
    ->name('tournaments.status');

Route::post('tournaments/{tournament}/categories', [TournamentCategoryController::class, 'store'])
    ->name('tournaments.categories.store');

// La categoría se resuelve por su slug dentro del torneo: el identificador
// interno no viaja en la URL.
Route::put('tournaments/{tournament}/categories/{category:slug}', [TournamentCategoryController::class, 'update'])
    ->scopeBindings()
    ->name('tournaments.categories.update');
