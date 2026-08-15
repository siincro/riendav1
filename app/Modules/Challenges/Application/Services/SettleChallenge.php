<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Application\Services;

use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Shared\Domain\Exceptions\DomainException;

/**
 * Cierra un reto: congela al ganador que marque la clasificación en ese
 * momento.
 *
 * Mientras el reto está en juego el ganador es un cálculo vivo; al resolverlo
 * queda escrito, de modo que una corrección posterior de un tiempo no cambia
 * lo que ya se anunció.
 */
final class SettleChallenge
{
    public function settle(Challenge $challenge): Challenge
    {
        if (! $challenge->status->isOpen()) {
            throw new DomainException('Este reto ya no está en juego.');
        }

        $challenge->load(['challenger.standings', 'rival.standings']);

        if ($challenge->isTied()) {
            throw new DomainException(
                'Los dos vaqueros van iguales en aciertos y en tiempo: el reto no se puede resolver todavía.'
            );
        }

        $winner = $challenge->leader();

        if ($winner === null) {
            throw new DomainException(
                'Todavía no hay lazos acertados en la categoría: el reto no tiene ganador que congelar.'
            );
        }

        $challenge->forceFill([
            'winner_participant_id' => $winner->id,
            'status' => ChallengeStatus::Settled,
            'settled_at' => now(),
        ])->save();

        return $challenge->refresh();
    }

    public function cancel(Challenge $challenge): Challenge
    {
        if ($challenge->status === ChallengeStatus::Settled) {
            throw new DomainException('Un reto resuelto ya no se puede cancelar.');
        }

        $challenge->forceFill([
            'status' => ChallengeStatus::Cancelled,
            'winner_participant_id' => null,
            'settled_at' => null,
        ])->save();

        return $challenge->refresh();
    }
}
