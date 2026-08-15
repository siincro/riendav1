<?php

declare(strict_types=1);

use App\Modules\Tournaments\Presentation\Http\Controllers\PublicTournamentController;
use Illuminate\Support\Facades\Route;

Route::get('torneos', [PublicTournamentController::class, 'index'])
    ->name('tournaments.index');

Route::get('torneos/{tournament:slug}', [PublicTournamentController::class, 'show'])
    ->name('tournaments.show');

// La categoría se resuelve por su slug dentro del torneo; el id interno no
// viaja en la URL pública.
Route::get('torneos/{tournament:slug}/categorias/{category:slug}', [PublicTournamentController::class, 'category'])
    ->scopeBindings()
    ->name('tournaments.categories.show');
