<?php

declare(strict_types=1);

namespace App\Modules\Public\Application\Services;

use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Collection;

/**
 * Arma la información de la landing pública.
 *
 * La landing es la única vista pública que cruza módulos (torneos,
 * clasificaciones, resultados y jinetes), así que vive en el módulo Public
 * como una operación de solo lectura sobre las entidades existentes.
 */
final class BuildLandingData
{
    /**
     * @return array{
     *     active: array<string, mixed>|null,
     *     upcoming: Collection<int, Tournament>,
     *     latest_results: Collection<int, RoundResult>,
     *     featured: Collection<int, Participant>,
     * }
     */
    public function handle(): array
    {
        return [
            'active' => $this->activeBlock(),
            'upcoming' => $this->upcomingTournaments(),
            'latest_results' => $this->latestResults(),
            'featured' => $this->featuredRiders(),
        ];
    }

    /**
     * El bloque "EN VIVO": el torneo en progreso más reciente y un marcador
     * por cada categoría que esté corriendo, con su día, su ronda y los
     * primeros puestos de su clasificación.
     *
     * Un torneo casi nunca corre una sola categoría —una feria mueve varias a
     * la vez—, así que la landing las trae todas y deja que el visitante
     * cambie entre ellas sin recargar.
     *
     * @return array<string, mixed>|null
     */
    private function activeBlock(): ?array
    {
        $tournament = Tournament::query()
            ->where('status', TournamentStatus::InProgress)
            ->orderByDesc('id')
            ->first();

        if ($tournament === null) {
            return null;
        }

        $categories = $tournament->categories()->withCount('registrations')->get();

        if ($categories->isEmpty()) {
            return null;
        }

        return [
            'tournament' => [
                'public_id' => $tournament->public_id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'location' => $tournament->location,
                'start_date' => $tournament->start_date?->toDateString(),
                'end_date' => $tournament->end_date?->toDateString(),
            ],
            // El cartel anuncia el torneo entero: todas las categorías viajan
            // para pintarse como etiquetas y para sumar los inscritos.
            'categories' => $categories
                ->map(fn (TournamentCategory $item): array => [
                    'slug' => $item->slug,
                    'name' => $item->name,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'registrations_count' => $item->registrations_count,
                ])
                ->values()
                ->all(),
            'boards' => $this->liveBoards($categories)
                ->map(fn (TournamentCategory $category): array => $this->board($category))
                ->values()
                ->all(),
        ];
    }

    /**
     * Las categorías con marcador en pantalla: las que están en progreso y,
     * si todavía no arrancó ninguna, la primera para que el cartel no quede
     * mudo mientras el torneo se prepara.
     *
     * @param  Collection<int, TournamentCategory>  $categories
     * @return Collection<int, TournamentCategory>
     */
    private function liveBoards(Collection $categories): Collection
    {
        $live = $categories->filter(
            fn (TournamentCategory $category): bool => $category->status === CategoryStatus::InProgress
        );

        return $live->isNotEmpty() ? $live : $categories->take(1);
    }

    /**
     * El marcador de una categoría: dónde va la competencia y quién manda.
     *
     * @return array<string, mixed>
     */
    private function board(TournamentCategory $category): array
    {
        $day = $category->competitionDays()
            ->where('status', CompetitionDayStatus::Active)
            ->with(['rounds' => fn ($query) => $query->where('status', RoundStatus::Active)])
            ->orderBy('day_number')
            ->first();

        $round = $day?->rounds->first();

        return [
            'category' => [
                'slug' => $category->slug,
                'name' => $category->name,
                'status' => $category->status->value,
                'status_label' => $category->status->label(),
                'registrations_count' => $category->registrations_count,
            ],
            'day' => $day === null ? null : [
                'day_number' => $day->day_number,
                'date' => $day->date?->toDateString(),
            ],
            'round' => $round === null ? null : [
                'round_number' => $round->round_number,
                'is_final' => $round->is_final,
            ],
            'standings' => $this->standingsPreview($category),
        ];
    }

    /**
     * @return Collection<int, CategoryStanding>
     */
    private function standingsPreview(TournamentCategory $category): Collection
    {
        return $category->standings()
            ->with(['registration.participant', 'registration.horse'])
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderByDesc('total_successful_rounds')
            ->orderBy('total_time_ms')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, Tournament>
     */
    private function upcomingTournaments(): Collection
    {
        return Tournament::query()
            ->where('status', TournamentStatus::Published)
            ->withCount('categories')
            ->orderBy('start_date')
            ->orderBy('id')
            ->take(6)
            ->get();
    }

    /**
     * @return Collection<int, RoundResult>
     */
    private function latestResults(): Collection
    {
        return RoundResult::query()
            ->with(['round.competitionDay.category.tournament', 'registration.participant', 'registration.horse'])
            ->whereHas(
                'round.competitionDay.category.tournament',
                fn ($query) => $query->whereIn('status', [
                    TournamentStatus::Published->value,
                    TournamentStatus::InProgress->value,
                    TournamentStatus::Finished->value,
                ])
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(8)
            ->get();
    }

    /**
     * Jinetes destacados: primero por victorias, después por participaciones.
     *
     * @return Collection<int, Participant>
     */
    private function featuredRiders(): Collection
    {
        return Participant::query()
            ->active()
            ->withCount(['registrations', 'horses'])
            ->withCount(['registrations as wins_count' => fn ($query) => $query->whereHas(
                'standing',
                fn ($query) => $query->where('position', 1)
            )])
            ->orderByDesc('wins_count')
            ->orderByDesc('registrations_count')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(6)
            ->get();
    }
}
