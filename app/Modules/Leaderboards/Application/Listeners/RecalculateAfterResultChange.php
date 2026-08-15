<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Application\Listeners;

use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Application\Services\CalculateDailyResult;
use App\Modules\Leaderboards\Application\Services\CalculateLeaderboard;
use App\Modules\Leaderboards\Application\Services\CalculateTournamentResult;

/**
 * El recálculo en cascada del documento, en un solo lugar:
 *
 *   RoundResult
 *        ↓
 *   Recalcular resultado del día
 *        ↓
 *   Recalcular acumulado del torneo
 *        ↓
 *   Recalcular clasificación
 *
 * La clasificación nunca depende de información introducida a mano por el
 * administrador: siempre se deriva de los resultados de ronda.
 */
final class RecalculateAfterResultChange
{
    public function __construct(
        private readonly CalculateDailyResult $calculateDailyResult,
        private readonly CalculateTournamentResult $calculateTournamentResult,
        private readonly CalculateLeaderboard $calculateLeaderboard,
    ) {}

    public function handleRecorded(RoundResultRecorded $event): void
    {
        $this->recalculate($event->result);
    }

    public function handleUpdated(RoundResultUpdated $event): void
    {
        $this->recalculate($event->result);
    }

    public function recalculate(RoundResult $result): void
    {
        $result->loadMissing(['round.competitionDay.category', 'registration']);

        $day = $result->round->competitionDay;
        $registration = $result->registration;

        $this->calculateDailyResult->handle($day, $registration);
        $this->calculateTournamentResult->handle($registration);
        $this->calculateLeaderboard->handle($day->category);
    }
}
