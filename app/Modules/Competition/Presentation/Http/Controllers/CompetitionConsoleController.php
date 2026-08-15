<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Competition\Presentation\Http\Resources\CompetitionDayResource;
use App\Modules\Leaderboards\Presentation\Http\Resources\StandingResource;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use App\Modules\Tournaments\Presentation\Http\Resources\TournamentCategoryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La consola de competencia: la pantalla que el administrador tiene abierta
 * mientras se corre la categoría.
 *
 * Reúne en una sola vista la estructura de días y rondas, la ronda activa con
 * sus inscritos para cronometrar, y la clasificación que se actualiza a medida
 * que se registran tiempos.
 */
class CompetitionConsoleController extends Controller
{
    public function show(Request $request, Tournament $tournament, TournamentCategory $category): Response
    {
        $category->load('tournament');

        $days = $category->competitionDays()
            ->with(['rounds' => fn ($query) => $query->withCount('results')])
            ->get();

        $round = $this->resolveRound($request, $days);

        return Inertia::render('competition/Console', [
            'category' => (new TournamentCategoryResource($category))->resolve($request),
            'days' => CompetitionDayResource::collection($days),
            'activeRound' => $round === null ? null : [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'day_number' => $round->competitionDay->day_number,
                'is_final' => $round->is_final,
                'status' => $round->status->value,
                'status_label' => $round->status->label(),
                'accepts_results' => $round->acceptsResults(),
            ],
            'entries' => $round === null ? [] : $this->entriesFor($category, $round),
            'standings' => StandingResource::collection(
                $category->standings()
                    ->with(['registration.participant', 'registration.horse'])
                    ->orderByRaw('position is null')
                    ->orderBy('position')
                    ->orderByDesc('total_successful_rounds')
                    ->orderBy('total_time_ms')
                    ->get()
            ),
        ]);
    }

    public function startDay(
        Tournament $tournament,
        TournamentCategory $category,
        CompetitionDay $day,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $lifecycle->startDay($day);

        return back()->with('success', "Día {$day->day_number} iniciado.");
    }

    public function completeDay(
        Tournament $tournament,
        TournamentCategory $category,
        CompetitionDay $day,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $lifecycle->completeDay($day);

        return back()->with('success', "Día {$day->day_number} finalizado.");
    }

    public function startRound(
        Tournament $tournament,
        TournamentCategory $category,
        Round $round,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $lifecycle->startRound($round);

        return back()->with('success', "Lazo {$round->round_number} en curso.");
    }

    public function completeRound(
        Tournament $tournament,
        TournamentCategory $category,
        Round $round,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $lifecycle->completeRound($round);

        return back()->with('success', "Lazo {$round->round_number} finalizado.");
    }

    public function completeCategory(
        Tournament $tournament,
        TournamentCategory $category,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $lifecycle->completeCategory($category);

        return back()->with('success', "Categoría {$category->name} finalizada.");
    }

    /**
     * Ronda sobre la que se está trabajando: la pedida por la URL, o la
     * primera que esté en curso, o la primera pendiente.
     *
     * @param  Collection<int, CompetitionDay>  $days
     */
    protected function resolveRound(Request $request, $days): ?Round
    {
        $rounds = $days->flatMap(fn (CompetitionDay $day) => $day->rounds);

        if ($request->filled('round')) {
            $requested = $rounds->firstWhere('id', $request->integer('round'));

            if ($requested !== null) {
                return $requested;
            }
        }

        return $rounds->first(fn (Round $round) => $round->status->value === 'active')
            ?? $rounds->first(fn (Round $round) => $round->status->value === 'pending')
            ?? $rounds->last();
    }

    /**
     * Los inscritos de la categoría junto con su resultado en la ronda activa,
     * que es exactamente lo que el administrador necesita ver para cronometrar.
     *
     * En el lazo final solo se listan los clasificados: quien quedó fuera del
     * top 10 no aparece, porque su jornada ya terminó.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function entriesFor(TournamentCategory $category, Round $round): array
    {
        $results = RoundResult::query()
            ->where('round_id', $round->id)
            ->get()
            ->keyBy('registration_id');

        $registrations = $category->registrations()
            ->with(['participant', 'horse'])
            ->orderBy('registration_number');

        if ($round->is_final) {
            $registrations = $registrations->whereIn('id', $category->finalistRegistrationIds());
        }

        return $registrations
            ->get()
            ->map(function (Registration $registration) use ($results): array {
                $result = $results->get($registration->id);

                return [
                    'registration_id' => $registration->id,
                    'registration_number' => $registration->registration_number,
                    'status' => $registration->status->value,
                    'accepts_results' => $registration->acceptsResults(),
                    'participant' => [
                        'public_id' => $registration->participant->public_id,
                        'full_name' => $registration->participant->full_name,
                    ],
                    'horse' => $registration->horse?->name,
                    'result' => $result === null ? null : [
                        'id' => $result->id,
                        'time_ms' => $result->time_ms,
                        'time' => $result->time_formatted,
                        'penalty_ms' => $result->penalty_ms,
                        'penalty' => $result->penalty_formatted,
                        // Lo que de verdad suma al acumulado: cronómetro más
                        // castigo, o cero si el lazo se falló.
                        'total' => Milliseconds::fromInt($result->countableTimeMs())->format(),
                        'successful' => $result->successful,
                        'notes' => $result->notes,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
