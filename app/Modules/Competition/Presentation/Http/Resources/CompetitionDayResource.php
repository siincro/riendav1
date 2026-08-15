<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Resources;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompetitionDay
 */
class CompetitionDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'date' => $this->date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rounds' => $this->whenLoaded('rounds', fn (): array => $this->rounds
                ->map(fn (Round $round): array => [
                    'id' => $round->id,
                    'round_number' => $round->round_number,
                    'is_final' => $round->is_final,
                    'status' => $round->status->value,
                    'status_label' => $round->status->label(),
                    'accepts_results' => $round->acceptsResults(),
                    'results_count' => $round->results_count,
                    'started_at' => $round->started_at?->toIso8601String(),
                    'finished_at' => $round->finished_at?->toIso8601String(),
                ])
                ->values()
                ->all()),
        ];
    }
}
