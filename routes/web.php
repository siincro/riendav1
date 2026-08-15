<?php

use App\Modules\Public\Presentation\Http\Controllers\LandingController;
use App\Modules\Shared\Presentation\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';

/*
 * Las rutas del panel administrativo (prefijo /admin) las registra cada
 * módulo desde app/Modules/{Modulo}/Presentation/Routes/admin.php.
 * Ver App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider.
 */
