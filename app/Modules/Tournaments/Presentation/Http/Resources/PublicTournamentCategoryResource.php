<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Resources;

use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una categoría tal como se muestra al público: estructura, estado y número
 * de inscritos, sin detalles de administración.
 *
 * @mixin TournamentCategory
 */
class PublicTournamentCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'order' => $this->order,
            'number_of_days' => $this->number_of_days,
            'rounds_per_day' => $this->rounds_per_day,
            'total_rounds' => $this->totalRounds(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
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
