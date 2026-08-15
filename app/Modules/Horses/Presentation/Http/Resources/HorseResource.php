<?php

declare(strict_types=1);

namespace App\Modules\Horses\Presentation\Http\Resources;

use App\Modules\Horses\Domain\Models\Horse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Horse
 */
class HorseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'owner' => $this->owner,
            'breed' => $this->breed,
            'color' => $this->color,
            'sex' => $this->sex?->value,
            'sex_label' => $this->sex?->label(),
            'birth_date' => $this->birth_date?->toDateString(),
            'photo_url' => $this->photo_url,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'participant' => $this->whenLoaded('participant', fn (): ?array => $this->participant === null ? null : [
                'public_id' => $this->participant->public_id,
                'full_name' => $this->participant->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
