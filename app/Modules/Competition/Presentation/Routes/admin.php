<?php

declare(strict_types=1);

use App\Modules\Competition\Presentation\Http\Controllers\CompetitionConsoleController;
use App\Modules\Competition\Presentation\Http\Controllers\CompetitionGroupController;
use App\Modules\Competition\Presentation\Http\Controllers\RoundResultController;
use App\Modules\Competition\Presentation\Http\Controllers\TournamentCompetitionConsoleController;
use Illuminate\Support\Facades\Route;

// La consola del evento trabaja sobre el torneo completo: el operador no
// cambia de categoría para cronometrar, solo sigue el orden de salida.
Route::get('tournaments/{tournament}/competition', [TournamentCompetitionConsoleController::class, 'show'])
    ->name('tournaments.competition');

Route::post('tournaments/{tournament}/competition/results', [TournamentCompetitionConsoleController::class, 'storeResult'])
    ->name('tournaments.competition.results.store');

Route::put('tournaments/{tournament}/competition/results/{result}', [TournamentCompetitionConsoleController::class, 'updateResult'])
    ->name('tournaments.competition.results.update');

// El día y el lazo del vaquero en salida se arrancan desde la misma consola.
Route::put('tournaments/{tournament}/competition/days/{day}/start', [TournamentCompetitionConsoleController::class, 'startDay'])
    ->name('tournaments.competition.days.start');

Route::put('tournaments/{tournament}/competition/rounds/{round}/start', [TournamentCompetitionConsoleController::class, 'startRound'])
    ->name('tournaments.competition.rounds.start');

// La jornada completa se pone en marcha de una vez desde la consola central.
Route::put('tournaments/{tournament}/competition/start-all-days', [TournamentCompetitionConsoleController::class, 'startAllDays'])
    ->name('tournaments.competition.days.start-all');

Route::prefix('tournaments/{tournament}')
    ->name('tournaments.')
    ->scopeBindings()
    ->group(function (): void {
        Route::post('groups', [CompetitionGroupController::class, 'store'])->name('groups.store');
        Route::put('groups/{group}', [CompetitionGroupController::class, 'update'])->name('groups.update');
        Route::delete('groups/{group}', [CompetitionGroupController::class, 'destroy'])->name('groups.destroy');
    });

Route::prefix('tournaments/{tournament}/categories/{category:slug}')
    ->name('tournaments.categories.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('competition', [CompetitionConsoleController::class, 'show'])
            ->name('competition');

        Route::put('days/{day}/start', [CompetitionConsoleController::class, 'startDay'])
            ->name('days.start');

        Route::put('days/{day}/complete', [CompetitionConsoleController::class, 'completeDay'])
            ->name('days.complete');

        Route::put('rounds/{round}/start', [CompetitionConsoleController::class, 'startRound'])
            ->name('rounds.start');

        Route::put('rounds/{round}/complete', [CompetitionConsoleController::class, 'completeRound'])
            ->name('rounds.complete');

        Route::put('complete', [CompetitionConsoleController::class, 'completeCategory'])
            ->name('complete');

        // Registro y corrección de tiempos.
        Route::post('rounds/{round}/results', [RoundResultController::class, 'store'])
            ->name('rounds.results.store');

        Route::put('results/{result}', [RoundResultController::class, 'update'])
            ->name('results.update');
    });
