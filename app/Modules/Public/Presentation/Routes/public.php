<?php

declare(strict_types=1);

use App\Modules\Public\Presentation\Http\Controllers\TvController;
use Illuminate\Support\Facades\Route;

// RIENDA TV: overlay de OBS Studio. `/tv` sigue la categoría activa del torneo
// en curso; la URL con slugs fija una categoría concreta en la fuente de
// navegador de OBS. Solo lectura.
Route::get('tv', [TvController::class, 'index'])->name('tv.index');

Route::get('tv/{tournament:slug}/categorias/{category:slug}', [TvController::class, 'show'])
    ->scopeBindings()
    ->name('tv.categories.show');
