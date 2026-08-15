<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Presentation\Http\Resources;

use App\Modules\Registrations\Domain\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Registration
 */
class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'participant' => [
                'public_id' => $this->participant->public_id,
                'full_name' => $this->participant->full_name,
                'city' => $this->participant->city,
                'photo_url' => $this->participant->photo_url,
            ],
            'horse' => $this->horse === null ? null : [
                'public_id' => $this->horse->public_id,
                'name' => $this->horse->name,
            ],
            'competition_group' => $this->whenLoaded('competitionGroup', fn (): ?array => $this->competitionGroup === null ? null : [
                'id' => $this->competitionGroup->id,
                'name' => $this->competitionGroup->name,
                'order' => $this->competitionGroup->order,
            ]),
            'standing' => $this->whenLoaded('standing', fn (): ?array => $this->standing === null ? null : [
                'position' => $this->standing->position,
                'tied' => $this->standing->tied,
                'total_successful_rounds' => $this->standing->total_successful_rounds,
                'total_time_ms' => $this->standing->total_time_ms,
                'total_time_formatted' => $this->standing->total_time_formatted,
            ]),
        ];
    }
}
