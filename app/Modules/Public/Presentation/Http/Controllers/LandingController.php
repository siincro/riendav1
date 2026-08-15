<?php

declare(strict_types=1);

namespace App\Modules\Public\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Presentation\Http\Resources\PublicStandingResource;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Participants\Presentation\Http\Resources\PublicParticipantResource;
use App\Modules\Public\Application\Services\BuildLandingData;
use App\Modules\Tournaments\Presentation\Http\Resources\PublicTournamentResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La landing pública: la cara deportiva de RIENDA.
 *
 * No es una página corporativa: muestra en vivo el torneo activo con su
 * clasificación, los próximos torneos, los últimos resultados y los jinetes
 * destacados. Es la única vista pública que cruza módulos, así que vive en el
 * módulo Public igual que el dashboard administrativo vive en Shared.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, BuildLandingData $landing): Response
    {
        $data = $landing->handle();

        $active = $data['active'];

        if ($active !== null) {
            $active['boards'] = array_map(
                fn (array $board): array => [
                    ...$board,
                    'standings' => PublicStandingResource::collection($board['standings']),
                ],
                $active['boards'],
            );
        }

        return Inertia::render('public/Home', [
            'active' => $active,
            'upcoming' => PublicTournamentResource::collection($data['upcoming']),
            'latest_results' => $data['latest_results']
                ->map(fn (RoundResult $result): array => $this->latestResult($result))
                ->values()
                ->all(),
            'featured' => $data['featured']
                ->map(fn (Participant $participant): array => [
                    ...(new PublicParticipantResource($participant))->resolve($request),
                    'wins_count' => $participant->wins_count ?? 0,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function latestResult(RoundResult $result): array
    {
        $category = $result->round->competitionDay->category;

        return [
            'id' => $result->id,
            'time_formatted' => $result->time_formatted,
            'successful' => $result->successful,
            'created_at' => $result->created_at?->toIso8601String(),
            'participant' => [
                'public_id' => $result->registration->participant->public_id,
                'full_name' => $result->registration->participant->full_name,
            ],
            'horse' => $result->registration->horse?->name,
            'tournament' => [
                'slug' => $category->tournament->slug,
                'name' => $category->tournament->name,
            ],
            'category' => [
                'slug' => $category->slug,
                'name' => $category->name,
            ],
            'day_number' => $result->round->competitionDay->day_number,
            'round_number' => $result->round->round_number,
        ];
    }
}
