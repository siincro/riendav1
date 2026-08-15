<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Presentation\Http\Resources;

use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Participants\Domain\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un reto tal como se muestra: los dos vaqueros con lo que llevan acumulado en
 * su categoría y quién va ganando.
 *
 * Espera el reto con `tournament`, `challenger.standings.category` y su gemelo
 * para el rival ya cargados.
 *
 * @mixin Challenge
 */
class ChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $leader = $this->status->isOpen() ? $this->leader() : $this->winner;

        return [
            'public_id' => $this->public_id,
            'tournament' => [
                'slug' => $this->tournament->slug,
                'name' => $this->tournament->name,
            ],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_tied' => $this->isTied(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'contenders' => array_map(
                fn (Participant $participant): array => $this->contender($participant, $leader),
                $this->contenders()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contender(Participant $participant, ?Participant $leader): array
    {
        $standing = $this->standingOf($participant);
        $category = $standing?->category;

        return [
            'participant' => [
                'public_id' => $participant->public_id,
                'full_name' => $participant->full_name,
                'photo_url' => $participant->photo_url,
            ],
            // A cada vaquero se le mide en la categoría que corre dentro del
            // evento: viaja para decir con qué se le está comparando.
            'category' => $category === null ? null : [
                'slug' => $category->slug,
                'name' => $category->name,
            ],
            // Sin clasificación todavía: cero acumulado y sin tiempo que mostrar.
            'total_time_ms' => $standing === null ? 0 : $standing->total_time_ms,
            'total_time_formatted' => $standing?->total_time_formatted,
            'total_successful_rounds' => $standing === null ? 0 : $standing->total_successful_rounds,
            // Va ganando (reto en juego) o ganó (reto resuelto): la interfaz
            // lo pinta igual, el estado del reto dice cuál de las dos.
            'is_leader' => $leader !== null && $leader->id === $participant->id,
        ];
    }
}
