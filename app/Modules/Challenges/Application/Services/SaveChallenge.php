<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Application\Services;

use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\Tournament;

/**
 * Arma un reto entre dos vaqueros de un mismo evento.
 *
 * La categoría no importa —pueden correr distintas y a cada uno se le mide con
 * la suya—, pero el evento sí: solo se retan vaqueros que estén inscritos en
 * él, que es de donde salen los tiempos que deciden.
 */
final class SaveChallenge
{
    public function create(
        Tournament $tournament,
        Participant $challenger,
        Participant $rival
    ): Challenge {
        $this->guard($tournament, $challenger, $rival);

        return Challenge::withPublicIdRetries(fn (): Challenge => Challenge::query()->create([
            'tournament_id' => $tournament->id,
            'challenger_participant_id' => $challenger->id,
            'rival_participant_id' => $rival->id,
            'status' => ChallengeStatus::Open,
        ]));
    }

    private function guard(
        Tournament $tournament,
        Participant $challenger,
        Participant $rival
    ): void {
        if ($challenger->id === $rival->id) {
            throw new DomainException(
                'Un vaquero no puede retarse a sí mismo.',
                ['rival_public_id' => 'Elija a un rival distinto.'],
            );
        }

        foreach ([$challenger, $rival] as $participant) {
            if (! $participant->isActive()) {
                throw new DomainException(
                    "{$participant->full_name} está inactivo y no puede entrar en un reto.",
                    ['rival_public_id' => 'Vaquero inactivo.'],
                );
            }

            if (! $this->competesIn($tournament, $participant)) {
                throw new DomainException(
                    "{$participant->full_name} no está inscrito en {$tournament->name}.",
                    ['rival_public_id' => 'El vaquero no corre este evento.'],
                );
            }
        }

        if ($this->alreadyFacing($tournament, $challenger, $rival)) {
            throw new DomainException(
                'Estos dos vaqueros ya tienen un reto en este evento.',
                ['rival_public_id' => 'El reto ya existe.'],
            );
        }
    }

    /**
     * El vaquero corre alguna categoría del evento con inscripción activa.
     */
    private function competesIn(Tournament $tournament, Participant $participant): bool
    {
        return $tournament->registrations()
            ->active()
            ->where('registrations.participant_id', $participant->id)
            ->exists();
    }

    /**
     * El par ya está enfrentado en este evento, sin importar quién retó.
     */
    private function alreadyFacing(
        Tournament $tournament,
        Participant $challenger,
        Participant $rival
    ): bool {
        return Challenge::query()
            ->where('tournament_id', $tournament->id)
            ->whereIn('challenger_participant_id', [$challenger->id, $rival->id])
            ->whereIn('rival_participant_id', [$challenger->id, $rival->id])
            ->exists();
    }
}
