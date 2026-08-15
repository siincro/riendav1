<?php

declare(strict_types=1);

namespace App\Modules\Participants\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Challenges\Presentation\Http\Resources\ChallengeResource;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Participants\Presentation\Http\Resources\PublicParticipantResource;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * La cara pública de los jinetes: directorio y perfil deportivo.
 *
 * Solo se muestran participantes activos; el perfil expone el historial
 * deportivo (torneos y posiciones finales), nunca datos sensibles.
 */
class PublicParticipantController extends Controller
{
    public function index(Request $request): Response
    {
        $participants = Participant::query()
            ->active()
            ->searchPublic($request->string('search')->toString())
            ->withCount(['horses', 'registrations'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(18)
            ->withQueryString();

        return Inertia::render('public/participants/Index', [
            'participants' => PublicParticipantResource::collection($participants),
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function show(Request $request, Participant $participant): Response
    {
        if (! $participant->isActive()) {
            throw new NotFoundHttpException;
        }

        $participant->load('horses');

        $history = $participant->registrations()
            ->with(['category.tournament', 'horse', 'standing'])
            ->whereHas(
                'category.tournament',
                fn ($query) => $query->whereIn('status', [
                    TournamentStatus::Published->value,
                    TournamentStatus::InProgress->value,
                    TournamentStatus::Finished->value,
                ])
            )
            ->orderByDesc('id')
            ->get();

        return Inertia::render('public/participants/Show', [
            'participant' => (new PublicParticipantResource($participant))->resolve($request),
            // Los retos son entre vaqueros, así que su casa natural es la
            // ficha de cada uno.
            'challenges' => ChallengeResource::collection($this->challengesOf($participant)),
            'horses' => $participant->horses->map(fn ($horse): array => [
                'name' => $horse->name,
                'breed' => $horse->breed,
                'color' => $horse->color,
            ])->values()->all(),
            'history' => $history->map(fn (Registration $registration): array => [
                'tournament' => [
                    'public_id' => $registration->category->tournament->public_id,
                    'name' => $registration->category->tournament->name,
                    'slug' => $registration->category->tournament->slug,
                    'start_date' => $registration->category->tournament->start_date?->toDateString(),
                    'status' => $registration->category->tournament->status->value,
                    'status_label' => $registration->category->tournament->status->label(),
                ],
                'category' => [
                    'slug' => $registration->category->slug,
                    'name' => $registration->category->name,
                ],
                'registration_number' => $registration->registration_number,
                'horse' => $registration->horse?->name,
                'standing' => $registration->standing === null ? null : [
                    'position' => $registration->standing->position,
                    'total_successful_rounds' => $registration->standing->total_successful_rounds,
                    'total_time_formatted' => $registration->standing->total_time_formatted,
                ],
            ])->values()->all(),
        ]);
    }

    /**
     * Los retos del vaquero, en juego o resueltos; los cancelados no se
     * anuncian.
     *
     * @return Collection<int, Challenge>
     */
    private function challengesOf(Participant $participant): Collection
    {
        return Challenge::query()
            ->whereIn('status', [ChallengeStatus::Open->value, ChallengeStatus::Settled->value])
            ->where(fn ($query) => $query
                ->where('challenger_participant_id', $participant->id)
                ->orWhere('rival_participant_id', $participant->id))
            ->with([
                'tournament',
                'challenger.standings.category',
                'rival.standings.category',
                'winner',
            ])
            ->orderByDesc('id')
            ->get();
    }
}
