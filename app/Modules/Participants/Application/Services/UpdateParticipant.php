<?php

declare(strict_types=1);

namespace App\Modules\Participants\Application\Services;

use App\Modules\Participants\Application\Data\ParticipantData;
use App\Modules\Participants\Domain\Models\Participant;

/**
 * Actualiza los datos de perfil de un participante.
 *
 * Cambiar estos datos nunca altera resultados oficiales ya registrados: el
 * historial deportivo cuelga de las inscripciones, no del perfil.
 */
final class UpdateParticipant
{
    public function handle(Participant $participant, ParticipantData $data): Participant
    {
        $participant->fill($data->toAttributes())->save();

        return $participant->refresh();
    }
}
