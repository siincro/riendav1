<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Application\Services;

use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;

/**
 * Controla el avance del ciclo de vida de un torneo.
 *
 * El estado no se edita como un campo más del formulario: cada transición es
 * una decisión con consecuencias (publicar hace visible el torneo, iniciar lo
 * pone en vivo, finalizar congela la clasificación).
 */
final class ChangeTournamentStatus
{
    public function handle(Tournament $tournament, TournamentStatus $target): Tournament
    {
        $current = $tournament->status;

        if ($current === $target) {
            return $tournament;
        }

        if (! $current->canTransitionTo($target)) {
            throw new DomainException(
                "No se puede pasar un evento de «{$current->label()}» a «{$target->label()}».",
                ['status' => "Transición no permitida: {$current->label()} → {$target->label()}."],
            );
        }

        if ($target === TournamentStatus::Published && $tournament->categories()->count() === 0) {
            throw new DomainException(
                'Un evento no puede publicarse sin categorías configuradas.',
                ['status' => 'Configure al menos una categoría antes de publicar el evento.'],
            );
        }

        $tournament->status = $target;
        $tournament->save();

        return $tournament->refresh();
    }
}
