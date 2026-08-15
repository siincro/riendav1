<?php

declare(strict_types=1);

use App\Modules\Participants\Presentation\Http\Controllers\PublicParticipantController;
use Illuminate\Support\Facades\Route;

Route::get('jinetes', [PublicParticipantController::class, 'index'])
    ->name('participants.index');

Route::get('jinetes/{participant:public_id}', [PublicParticipantController::class, 'show'])
    ->name('participants.show');
