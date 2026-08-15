<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Listeners;

use App\Modules\Competition\Application\Services\BuildCategorySchedule;
use App\Modules\Tournaments\Domain\Events\CategoryStructureDefined;

/**
 * Puente entre Tournaments y Competition: cuando una categoría define su
 * estructura, este módulo crea los días y las rondas correspondientes.
 */
final class BuildScheduleForCategory
{
    public function __construct(private readonly BuildCategorySchedule $buildSchedule) {}

    public function handle(CategoryStructureDefined $event): void
    {
        $this->buildSchedule->handle($event->category);
    }
}
