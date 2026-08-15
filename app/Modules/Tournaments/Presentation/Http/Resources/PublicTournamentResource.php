<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Resources;

use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un torneo tal como se muestra al público.
 *
 * Misma información que el recurso administrativo pero sin datos de
 * administración: no expone transiciones de estado ni el id interno.
 *
 * @mixin Tournament
 */
class PublicTournamentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_live' => $this->status->isLive(),
            'cover_image_url' => $this->cover_image_url,
            'categories_count' => $this->whenCounted('categories'),
            'registrations_count' => $this->whenCounted('registrations'),
            // Arreglo plano: la cara pública nunca envuelve colecciones anidadas
            // en un envoltorio «data».
            'categories' => $this->whenLoaded('categories', fn (): array => $this->categories
                ->map(fn (TournamentCategory $category) => (new PublicTournamentCategoryResource($category))->resolve($request))
                ->values()
                ->all()),
        ];
    }
}
