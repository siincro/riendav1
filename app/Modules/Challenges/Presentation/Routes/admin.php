<?php

declare(strict_types=1);

use App\Modules\Challenges\Presentation\Http\Controllers\ChallengeController;
use Illuminate\Support\Facades\Route;

Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::post('challenges', [ChallengeController::class, 'store'])->name('challenges.store');

// El reto se resuelve —congelando al ganador— o se cancela; el identificador
// que viaja en la URL es el público (RETO-000001).
Route::put('challenges/{challenge}/settle', [ChallengeController::class, 'settle'])->name('challenges.settle');
Route::put('challenges/{challenge}/cancel', [ChallengeController::class, 'cancel'])->name('challenges.cancel');
Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy'])->name('challenges.destroy');
