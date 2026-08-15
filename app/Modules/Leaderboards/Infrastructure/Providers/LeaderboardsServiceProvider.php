<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Infrastructure\Providers;

use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Leaderboards\Application\Listeners\RecalculateAfterResultChange;
use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class LeaderboardsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Leaderboards';
    }

    public function boot(): void
    {
        parent::boot();

        // Toda modificación de un resultado dispara el recálculo en cascada:
        // día → acumulado → clasificación.
        Event::listen(RoundResultRecorded::class, [RecalculateAfterResultChange::class, 'handleRecorded']);
        Event::listen(RoundResultUpdated::class, [RecalculateAfterResultChange::class, 'handleUpdated']);
    }
}
