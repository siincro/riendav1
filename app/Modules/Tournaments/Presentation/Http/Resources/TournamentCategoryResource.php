<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Resources;

use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TournamentCategory
 */
class TournamentCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'order' => $this->order,
            'number_of_days' => $this->number_of_days,
            'rounds_per_day' => $this->rounds_per_day,
            'total_rounds' => $this->totalRounds(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'structure_is_editable' => $this->structureIsEditable(),
            'accepts_registrations' => $this->relationLoaded('tournament')
                ? $this->acceptsRegistrations()
                : $this->status->acceptsRegistrations(),
            'registrations_count' => $this->whenCounted('registrations'),
            'tournament' => $this->whenLoaded('tournament', fn (): array => [
                'slug' => $this->tournament->slug,
                'name' => $this->tournament->name,
                'public_id' => $this->tournament->public_id,
                'status' => $this->tournament->status->value,
            ]),
        ];
    }
}
