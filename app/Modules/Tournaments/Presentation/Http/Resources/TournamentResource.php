<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Resources;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tournament
 */
class TournamentResource extends JsonResource
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
            'allowed_transitions' => array_map(
                fn ($status): array => ['value' => $status->value, 'label' => $status->label()],
                $this->status->allowedTransitions()
            ),
            'categories_count' => $this->whenCounted('categories'),
            'registrations_count' => $this->whenCounted('registrations'),
            // La regla de si un torneo se puede borrar la decide el dominio;
            // el panel solo pinta o esconde la acción.
            'can_be_deleted' => $this->canBeDeleted(),
            // Arreglo plano: los recursos anidados se resuelven aquí mismo para
            // no quedar envueltos en un envoltorio «data».
            'categories' => $this->whenLoaded('categories', fn (): array => $this->categories
                ->map(fn (TournamentCategory $category) => (new TournamentCategoryResource($category))->resolve($request))
                ->values()
                ->all()),
            'competition_groups' => $this->whenLoaded('competitionGroups', fn (): array => $this->competitionGroups
                ->map(fn (CompetitionGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'order' => $group->order,
                    'registrations_count' => $group->registrations_count,
                ])
                ->values()
                ->all()),
        ];
    }
}
