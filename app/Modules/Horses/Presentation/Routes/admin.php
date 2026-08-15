<?php

declare(strict_types=1);

use App\Modules\Horses\Presentation\Http\Controllers\HorseController;
use Illuminate\Support\Facades\Route;

Route::resource('horses', HorseController::class)
    ->parameters(['horses' => 'horse'])
    ->except(['show', 'destroy']);
