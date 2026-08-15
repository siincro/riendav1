<?php

declare(strict_types=1);

use App\Modules\Participants\Presentation\Http\Controllers\ParticipantController;
use Illuminate\Support\Facades\Route;

Route::resource('participants', ParticipantController::class)
    ->parameters(['participants' => 'participant'])
    ->except(['destroy']);
