<?php

declare(strict_types=1);

namespace App\Modules\Participants\Presentation\Http\Resources;

use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un jinete tal como se muestra al público.
 *
 * Nunca expone documento, teléfono ni el id interno: el perfil público de
 * RIENDA es la carta deportiva del jinete, no su ficha administrativa.
 *
 * @mixin Participant
 */
class PublicParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'full_name' => $this->full_name,
            'city' => $this->city,
            'country' => $this->country,
            'photo_url' => $this->photo_url,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'horses_count' => $this->whenCounted('horses'),
            'registrations_count' => $this->whenCounted('registrations'),
        ];
    }
}
