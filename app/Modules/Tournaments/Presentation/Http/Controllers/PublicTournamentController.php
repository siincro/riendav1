<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Challenges\Domain\Enums\ChallengeStatus;
use App\Modules\Challenges\Domain\Models\Challenge;
use App\Modules\Challenges\Presentation\Http\Resources\ChallengeResource;
use App\Modules\Leaderboards\Application\Services\GetCategoryLeaderboard;
use App\Modules\Leaderboards\Presentation\Http\Resources\PublicStandingResource;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use App\Modules\Tournaments\Presentation\Http\Resources\PublicTournamentCategoryResource;
use App\Modules\Tournaments\Presentation\Http\Resources\PublicTournamentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * La cara pública de los torneos: listado, detalle y la clasificación de cada
 * categoría. Solo lectura, sin datos de administración.
 */
class PublicTournamentController extends Controller
{
    public function index(Request $request): Response
    {
        $tournaments = Tournament::query()
            ->publiclyVisible()
            ->search($request->string('search')->toString())
            ->withCount(['categories', 'registrations'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('public/tournaments/Index', [
            'tournaments' => PublicTournamentResource::collection($tournaments),
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }

    public function show(Request $request, Tournament $tournament): Response
    {
        $this->guardVisible($tournament);

        $tournament->load([
            'categories' => fn ($query) => $query->withCount('registrations'),
        ]);

        return Inertia::render('public/tournaments/Show', [
            'tournament' => (new PublicTournamentResource($tournament))->resolve($request),
        ]);
    }

    public function category(
        Request $request,
        Tournament $tournament,
        TournamentCategory $category,
        GetCategoryLeaderboard $leaderboard
    ): Response {
        $this->guardVisible($tournament);

        $category->load('tournament')->loadCount('registrations');

        $data = $leaderboard->handle($category);

        return Inertia::render('public/tournaments/Category', [
            'category' => (new PublicTournamentCategoryResource($category))->resolve($request),
            'standings' => PublicStandingResource::collection($data['standings']),
            'days' => $data['days'],
            'rows' => $data['rows']->values()->all(),
            // Los retos de la categoría se leen de la misma clasificación, así
            // que viajan con ella y se refrescan con los mismos eventos.
            'challenges' => ChallengeResource::collection($this->challengesFor($category)),
        ]);
    }

    /**
     * Los retos que tocan a esta categoría: los de cualquiera de sus inscritos.
     *
     * Un reto ya no vive dentro de una categoría —enfrenta a dos vaqueros
     * corran lo que corran—, así que aquí se listan los de quienes están en
     * esta pista. Los cancelados no se anuncian.
     *
     * @return Collection<int, Challenge>
     */
    private function challengesFor(TournamentCategory $category): Collection
    {
        $contenders = $category->registrations()->select('participant_id');

        return Challenge::query()
            ->whereIn('status', [ChallengeStatus::Open->value, ChallengeStatus::Settled->value])
            ->where(fn ($query) => $query
                ->whereIn('challenger_participant_id', $contenders)
                ->orWhereIn('rival_participant_id', $contenders))
            ->with([
                'tournament',
                'challenger.standings.category',
                'rival.standings.category',
                'winner',
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Un torneo en borrador no se filtra al público.
     */
    private function guardVisible(Tournament $tournament): void
    {
        if (! $tournament->status->isPubliclyVisible()) {
            throw new NotFoundHttpException;
        }
    }
}
