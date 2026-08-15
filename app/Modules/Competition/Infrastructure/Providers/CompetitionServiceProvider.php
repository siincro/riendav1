<?php

declare(strict_types=1);

namespace App\Modules\Competition\Infrastructure\Providers;

use App\Modules\Competition\Application\Listeners\BuildScheduleForCategory;
use App\Modules\Competition\Application\Listeners\CompleteRoundWhenEveryoneRecorded;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;
use App\Modules\Tournaments\Domain\Events\CategoryStructureDefined;
use Illuminate\Support\Facades\Event;

class CompetitionServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Competition';
    }

    public function boot(): void
    {
        parent::boot();

        // Materializa días y rondas cuando una categoría define su estructura.
        Event::listen(CategoryStructureDefined::class, BuildScheduleForCategory::class);

        // Cuando todos los inscritos activos registran su resultado, la ronda
        // se cierra sola y la siguiente pendiente pasa a ser la activa.
        Event::listen(RoundResultRecorded::class, CompleteRoundWhenEveryoneRecorded::class);
    }
}
