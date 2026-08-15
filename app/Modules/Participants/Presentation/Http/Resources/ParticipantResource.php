<?php

declare(strict_types=1);

namespace App\Modules\Participants\Presentation\Http\Resources;

use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Participant
 */
class ParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Nunca se expone el id interno: la referencia pública es public_id.
            'public_id' => $this->public_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'document' => $this->document,
            'phone' => $this->phone,
            'city' => $this->city,
            'country' => $this->country,
            'photo_url' => $this->photo_url,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'horses_count' => $this->whenCounted('horses'),
            'registrations_count' => $this->whenCounted('registrations'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
