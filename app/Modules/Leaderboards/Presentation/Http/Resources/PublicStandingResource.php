<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Presentation\Http\Resources;

use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una posición de la clasificación tal como se muestra al público.
 *
 * Igual que el recurso administrativo pero sin el id interno de la
 * inscripción: la referencia pública del jinete es su `public_id`.
 *
 * @mixin CategoryStanding
 */
class PublicStandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'tied' => $this->tied,
            'total_successful_rounds' => $this->total_successful_rounds,
            'total_time_ms' => $this->total_time_ms,
            'total_time_formatted' => $this->total_time_formatted,
            // El acumulado ya incluye los castigos; esto dice cuánto de él lo es.
            'total_penalty_ms' => $this->total_penalty_ms,
            'total_penalty_formatted' => $this->total_penalty_formatted,
            'recorded_rounds' => $this->recorded_rounds,
            'registration' => $this->whenLoaded('registration', fn (): array => [
                'registration_number' => $this->registration->registration_number,
                'participant' => [
                    'public_id' => $this->registration->participant->public_id,
                    'full_name' => $this->registration->participant->full_name,
                ],
                'horse' => $this->registration->horse?->name,
            ]),
        ];
    }
}
