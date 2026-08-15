<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Challenges\Application\Services\SaveChallenge;
use App\Modules\Challenges\Application\Services\SettleChallenge;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Challenges\Presentation\Http\Requests\ChallengeRequest;
use App\Modules\Challenges\Presentation\Http\Resources\ChallengeResource;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los retos vistos desde el panel: crearlos, resolverlos y cancelarlos.
 */
class ChallengeController extends Controller
{
    public function index(): Response
    {
        $challenges = Challenge::query()
            ->with(self::RELATIONS)
            ->orderByDesc('id')
            ->get();

        return Inertia::render('challenges/Index', [
            'challenges' => ChallengeResource::collection($challenges),
            // Para armar un reto se elige el evento y dos de sus inscritos:
            // la categoría que corre cada uno la resuelve el propio reto.
            'events' => $this->challengeableEvents(),
        ]);
    }

    public function store(ChallengeRequest $request, SaveChallenge $saveChallenge): RedirectResponse
    {
        $validated = $request->validated();

        $tournament = Tournament::query()->where('slug', $validated['tournament_slug'])->firstOrFail();

        $challenger = $this->participant($validated['challenger_public_id']);
        $rival = $this->participant($validated['rival_public_id']);

        $challenge = $saveChallenge->create($tournament, $challenger, $rival);

        return back()->with(
            'success',
            "Reto {$challenge->public_id}: {$challenger->full_name} contra {$rival->full_name}."
        );
    }

    public function settle(Challenge $challenge, SettleChallenge $settleChallenge): RedirectResponse
    {
        $challenge = $settleChallenge->settle($challenge);

        $winner = $challenge->winner?->full_name;

        return back()->with('success', "Reto {$challenge->public_id} resuelto: gana {$winner}.");
    }

    public function cancel(Challenge $challenge, SettleChallenge $settleChallenge): RedirectResponse
    {
        $settleChallenge->cancel($challenge);

        return back()->with('success', "Reto {$challenge->public_id} cancelado.");
    }

    public function destroy(Challenge $challenge): RedirectResponse
    {
        $challenge->delete();

        return back()->with('success', "Reto {$challenge->public_id} eliminado.");
    }

    /**
     * Lo que hay que cargar para poder decidir un reto: el acumulado de cada
     * vaquero con la categoría de la que sale.
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'tournament',
        'challenger.standings.category',
        'rival.standings.category',
        'winner',
    ];

    private function participant(string $publicId): Participant
    {
        return Participant::query()->where('public_id', $publicId)->firstOrFail();
    }

    /**
     * Los eventos que admiten retos —publicados o en curso— con los vaqueros
     * inscritos en cualquiera de sus categorías.
     *
     * @return list<array<string, mixed>>
     */
    private function challengeableEvents(): array
    {
        $events = Tournament::query()
            ->whereIn('status', [TournamentStatus::Published->value, TournamentStatus::InProgress->value])
            ->with(['registrations' => fn ($query) => $query->active()->with('participant')])
            ->orderBy('name')
            ->get()
            ->map(fn (Tournament $tournament): array => [
                'slug' => $tournament->slug,
                'name' => $tournament->name,
                // Un vaquero puede correr varias categorías del evento: en la
                // lista aparece una sola vez.
                'contenders' => $tournament->registrations
                    ->map(fn (Registration $registration): array => [
                        'public_id' => $registration->participant->public_id,
                        'full_name' => $registration->participant->full_name,
                    ])
                    ->unique('public_id')
                    ->sortBy('full_name')
                    ->values()
                    ->all(),
            ])
            ->all();

        return array_values($events);
    }
}
