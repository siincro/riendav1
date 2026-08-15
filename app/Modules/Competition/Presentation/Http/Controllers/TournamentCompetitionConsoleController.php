<?php

declare(strict_types=1);

namespace App\Modules\Competition\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Application\Services\BuildTournamentRunOrder;
use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Application\Services\RegisterRoundResult;
use App\Modules\Competition\Application\Services\UpdateRoundResult;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Competition\Presentation\Http\Requests\RoundResultRequest;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La consola del evento: una sola pantalla para cronometrar sin cambiar de
 * categoría. La cola viene del orden de salida por grupos; el resultado se
 * sigue guardando sobre la ronda real de la categoría del vaquero.
 */
class TournamentCompetitionConsoleController extends Controller
{
    public function show(
        Request $request,
        Tournament $tournament,
        BuildTournamentRunOrder $runOrder
    ): Response {
        $run = $runOrder->handle(
            $tournament,
            $request->filled('day') ? $request->integer('day') : null,
        );

        $current = $this->resolveCurrentEntry($run['queue'], $request);

        return Inertia::render('competition/TournamentConsole', [
            'tournament' => [
                'id' => $tournament->id,
                'public_id' => $tournament->public_id,
                'name' => $tournament->name,
                'slug' => $tournament->slug,
                'status' => $tournament->status->value,
                'status_label' => $tournament->status->label(),
            ],
            'days' => $run['days'],
            'selectedDayNumber' => $run['selected_day_number'],
            'queue' => $run['queue'],
            'currentEntry' => $current,
            'standings' => $this->standings($tournament),
        ]);
    }

    public function storeResult(
        RoundResultRequest $request,
        Tournament $tournament,
        ManageRoundLifecycle $lifecycle,
        RegisterRoundResult $registerResult
    ): RedirectResponse {
        $validated = [
            ...$request->validated(),
            ...$request->validate([
                'registration_id' => [
                    'required',
                    'integer',
                    Rule::exists('registrations', 'id')
                        ->whereIn('tournament_category_id', $tournament->categories()->pluck('id')),
                ],
            ], [], [
                'registration_id' => 'inscripción',
            ]),
        ];

        $registration = Registration::query()
            ->with(['category', 'participant'])
            ->findOrFail($request->integer('registration_id'));

        $round = $this->nextRoundForRegistration($registration, $request);

        if ($round === null) {
            throw new DomainException(
                'No queda un lazo pendiente para esta inscripción en el día seleccionado.',
                ['registration_id' => 'La inscripción no tiene una ronda pendiente en el día seleccionado.'],
            );
        }

        $this->guardRegistrationMatchesRound($registration, $round);

        if ($round->status->value === 'pending') {
            $round = $lifecycle->startRound($round);
        }

        $result = $registerResult->handle(
            $round,
            $registration,
            RoundResultData::fromValidated($validated, $request->user()?->id),
        );

        return back()->with('success', $this->message($registration, $result));
    }

    public function updateResult(
        RoundResultRequest $request,
        Tournament $tournament,
        RoundResult $result,
        UpdateRoundResult $updateResult
    ): RedirectResponse {
        $this->guardResultBelongsToTournament($tournament, $result);

        $previous = $result->time_formatted;

        $result = $updateResult->handle(
            $result,
            RoundResultData::fromValidated($request->validated(), $request->user()?->id),
        );

        return back()->with(
            'success',
            $result->successful
                ? "Resultado corregido: {$previous} → {$result->time_formatted}."
                : 'Resultado corregido: lazo marcado como fallido.'
        );
    }

    /**
     * El día del vaquero en salida se arranca sin salir de la consola. Al
     * iniciar el lazo, ManageRoundLifecycle arranca el día solo si está
     * pendiente, así que los dos botones cubren la puesta en marcha.
     */
    public function startDay(
        Tournament $tournament,
        CompetitionDay $day,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $this->guardDayBelongsToTournament($tournament, $day);

        $lifecycle->startDay($day);

        return back()->with('success', "Día {$day->day_number} iniciado.");
    }

    public function startRound(
        Tournament $tournament,
        Round $round,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $this->guardRoundBelongsToTournament($tournament, $round);

        $lifecycle->startRound($round);

        return back()->with('success', "Lazo {$round->round_number} en curso.");
    }

    /**
     * La jornada completa del torneo se arranca de una vez: inicia el día en
     * todas las categorías que tengan vaqueros en pista.
     */
    public function startAllDays(
        Request $request,
        Tournament $tournament,
        ManageRoundLifecycle $lifecycle
    ): RedirectResponse {
        $validated = $request->validate([
            'day' => ['required', 'integer', 'min:1'],
        ], [], ['day' => 'día']);

        $started = $lifecycle->startDayForAllCategories($tournament, $validated['day']);

        return back()->with(
            'success',
            $started > 0
                ? "Día {$validated['day']} iniciado en {$started} categorías."
                : "El día {$validated['day']} ya estaba en marcha."
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $queue
     * @return array<string, mixed>|null
     */
    private function resolveCurrentEntry(array $queue, Request $request): ?array
    {
        if ($queue === []) {
            return null;
        }

        if ($request->filled('entry')) {
            $entryId = $request->integer('entry');

            $selected = collect($queue)->firstWhere('registration_id', $entryId);

            if ($selected !== null) {
                return $selected;
            }
        }

        return collect($queue)->firstWhere('result', null) ?? $queue[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function standings(Tournament $tournament): array
    {
        return $tournament->categories()
            ->with(['standings' => fn ($query) => $query
                ->with(['registration.participant', 'registration.horse'])
                ->orderByRaw('position is null')
                ->orderBy('position')
                ->orderByDesc('total_successful_rounds')
                ->orderBy('total_time_ms')
                ->limit(5)])
            ->get()
            ->map(fn (TournamentCategory $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'status' => $category->status->value,
                'status_label' => $category->status->label(),
                'standings' => $category->standings
                    ->map(fn (CategoryStanding $standing): array => [
                        'position' => $standing->position,
                        'tied' => $standing->tied,
                        'total_successful_rounds' => $standing->total_successful_rounds,
                        'total_time_ms' => $standing->total_time_ms,
                        'total_time_formatted' => $standing->total_time_formatted,
                        'total_penalty_ms' => $standing->total_penalty_ms,
                        'total_penalty_formatted' => $standing->total_penalty_formatted,
                        'recorded_rounds' => $standing->recorded_rounds,
                        'registration' => [
                            'id' => $standing->registration_id,
                            'registration_number' => $standing->registration->registration_number,
                            'participant' => $standing->registration->participant->full_name,
                            'horse' => $standing->registration->horse?->name,
                        ],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function nextRoundForRegistration(
        Registration $registration,
        Request $request
    ): ?Round {
        $category = $registration->category;
        $dayNumber = $request->integer('day');

        if ($dayNumber <= 0) {
            $dayNumber = null;
        }

        $days = $category->competitionDays()->with('rounds')->get();

        $day = $dayNumber === null
            ? $days->first(fn ($candidate): bool => $candidate->status->value === 'active')
                ?? $days->first(fn ($candidate): bool => $candidate->status->value === 'pending')
                ?? $days->first()
            : $days->firstWhere('day_number', $dayNumber);

        if ($day === null) {
            return null;
        }

        $results = RoundResult::query()
            ->where('registration_id', $registration->id)
            ->whereIn('round_id', $day->rounds->pluck('id'))
            ->get()
            ->keyBy('round_id');

        $active = $day->rounds->firstWhere('status', 'active');

        $candidate = $active ?? $day->rounds->first(
            fn (Round $round): bool => $round->status->value === 'pending' && ! $results->has($round->id)
        );

        if ($candidate === null) {
            return null;
        }

        if ($active !== null && $results->has($active->id)) {
            return null;
        }

        // Al lazo final solo entra quien clasificó (top 10). Quien se quedó
        // fuera no tiene una ronda pendiente ese día.
        if ($candidate->is_final && ! $registration->category->isFinalist($registration->id)) {
            return null;
        }

        return $candidate;
    }

    /**
     * La inscripción solo puede registrar en la ronda de su propia categoría.
     */
    private function guardRegistrationMatchesRound(Registration $registration, Round $round): void
    {
        if ($registration->tournament_category_id !== $round->categoryId()) {
            throw new DomainException(
                'La inscripción no pertenece a la categoría de este lazo.',
                ['registration_id' => 'Inscripción y lazo corresponden a categorías distintas.'],
            );
        }
    }

    private function guardResultBelongsToTournament(Tournament $tournament, RoundResult $result): void
    {
        $exists = $tournament->categories()
            ->where('id', $result->round->competitionDay->tournament_category_id)
            ->exists();

        if (! $exists) {
            throw new DomainException('El resultado no pertenece a este evento.');
        }
    }

    private function guardDayBelongsToTournament(Tournament $tournament, CompetitionDay $day): void
    {
        $exists = $tournament->categories()
            ->where('id', $day->tournament_category_id)
            ->exists();

        if (! $exists) {
            throw new DomainException('El día no pertenece a este evento.');
        }
    }

    private function guardRoundBelongsToTournament(Tournament $tournament, Round $round): void
    {
        $exists = $tournament->categories()
            ->where('id', $round->competitionDay->tournament_category_id)
            ->exists();

        if (! $exists) {
            throw new DomainException('El lazo no pertenece a este evento.');
        }
    }

    private function message(Registration $registration, RoundResult $result): string
    {
        $name = $registration->participant->full_name;

        return $result->successful
            ? "{$name}: {$result->time_formatted} registrado."
            : "{$name}: lazo fallido registrado.";
    }
}
