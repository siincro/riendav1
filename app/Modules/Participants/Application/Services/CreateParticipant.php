<?php

declare(strict_types=1);

namespace App\Modules\Participants\Application\Services;

use App\Modules\Participants\Application\Data\ParticipantData;
use App\Modules\Participants\Domain\Models\Participant;

/**
 * Registra un participante permanente en RIENDA.
 *
 * El alta se hace una sola vez: a partir de aquí el jinete acumula historial
 * a través de sus inscripciones, sin volver a crearse por torneo.
 */
final class CreateParticipant
{
    public function handle(ParticipantData $data): Participant
    {
        return Participant::withPublicIdRetries(
            fn (): Participant => Participant::query()->create($data->toAttributes())
        );
    }
}
