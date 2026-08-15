<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\ValueObjects\Milliseconds;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Collection;

/**
 * Construye la cola operativa de salida de un torneo para un día concreto.
 *
 * La clasificación sigue calculándose por categoría; esto es distinto: es el
 * orden en el que los vaqueros salen a pista. Cada inscripción aporta su
 * siguiente ronda pendiente del día seleccionado, de modo que la consola del
 * evento pueda avanzar sin obligar al operador a cambiar de categoría.
 *
 * El primer día sale por grupos; desde el segundo día la pista se ordena por
 * categoría según el rendimiento acumulado: primero quien menos lazos ha
 * acertado y, a igualdad, quien más tiempo tiene registrado.
 */
final class BuildTournamentRunOrder
{
    /**
     * @return array{days: array<int, array<string, mixed>>, selected_day_number: int|null, queue: array<int, array<string, mixed>>}
     */
    public function handle(Tournament $tournament, ?int $requestedDayNumber = null): array
    {
        $tournament->loadMissing([
            'categories.competitionDays.rounds',
            'competitionGroups',
        ]);

        $days = $this->daySummaries($tournament);
        $selectedDayNumber = $this->resolveDayNumber($tournament, $requestedDayNumber);

        if ($selectedDayNumber === null) {
            return [
                'days' => $days,
                'selected_day_number' => null,
                'queue' => [],
            ];
        }

        return [
            'days' => $days,
            'selected_day_number' => $selectedDayNumber,
            'queue' => $this->queueFor($tournament, $selectedDayNumber),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function daySummaries(Tournament $tournament): array
    {
        return $tournament->categories
            ->flatMap(fn (TournamentCategory $category) => $category->competitionDays)
            ->groupBy('day_number')
            ->sortKeys()
            ->map(function (Collection $days, int|string $dayNumber): array {
                /** @var Collection<int, CompetitionDay> $days */
                $status = $this->summarizeStatus(array_values(
                    $days->map(fn (CompetitionDay $day): string => $day->status->value)->all()
                ));

                return [
                    'day_number' => (int) $dayNumber,
                    'date' => $days->firstWhere('date', '!=', null)?->date?->toDateString(),
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'rounds_count' => $days->sum(fn (CompetitionDay $day): int => $day->rounds->count()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Prioridad del día de trabajo: el pedido en la URL si existe; si no, el
     * primero que tenga algo activo; si no, el primer día pendiente; si no,
     * el primero disponible.
     */
    private function resolveDayNumber(Tournament $tournament, ?int $requestedDayNumber): ?int
    {
        $days = $tournament->categories
            ->flatMap(fn (TournamentCategory $category) => $category->competitionDays);

        if ($days->isEmpty()) {
            return null;
        }

        $dayNumbers = $days->pluck('day_number')->unique()->sort()->values();

        if ($requestedDayNumber !== null && $dayNumbers->contains($requestedDayNumber)) {
            return $requestedDayNumber;
        }

        $active = $days->first(fn (CompetitionDay $day): bool => $day->status->value === 'active'
            || $day->rounds->contains(fn (Round $round): bool => $round->status->value === 'active'));

        if ($active !== null) {
            return $active->day_number;
        }

        $pending = $days->first(fn (CompetitionDay $day): bool => $day->status->value === 'pending');

        return $pending !== null ? $pending->day_number : $dayNumbers->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function queueFor(Tournament $tournament, int $dayNumber): array
    {
        $registrations = Registration::query()
            ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->active()
            ->with(['participant', 'horse', 'competitionGroup', 'category'])
            ->get();

        $results = RoundResult::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->whereHas(
                'round.competitionDay',
                fn ($query) => $query->where('day_number', $dayNumber)
            )
            ->get()
            ->groupBy('registration_id');

        $standings = CategoryStanding::query()
            ->whereIn('tournament_category_id', $tournament->categories->pluck('id'))
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->get()
            ->keyBy('registration_id');

        $standingsOrder = $dayNumber > 1;

        return $registrations
            ->map(function (Registration $registration) use ($dayNumber, $results, $standings): ?array {
                $day = $registration->category->competitionDays
                    ->firstWhere('day_number', $dayNumber);

                if ($day === null) {
                    return null;
                }

                $round = $this->nextRoundFor(
                    $day,
                    $results->get($registration->id),
                    $standings->get($registration->id),
                );

                if ($round === null) {
                    return null;
                }

                return $this->entryFor(
                    $registration,
                    $day,
                    $round,
                    $standings->get($registration->id),
                );
            })
            ->filter()
            ->sort(fn (array $left, array $right): int => $this->compare($left, $right, $standingsOrder))
            ->values()
            ->map(fn (array $entry, int $index): array => [
                ...$entry,
                'sequence' => $index + 1,
            ])
            ->all();
    }

    /**
     * La siguiente ronda de la inscripción en el día.
     *
     * Si hay una ronda activa, es la de trabajo: entra a la cola solo quien
     * aún no registró en ella; quien ya salió espera a que cierre. Sin ronda
     * activa, la siguiente ronda pendiente sin resultado.
     *
     * El lazo final solo lo corre quien clasificó: los diez primeros de la
     * clasificación. Quien quedó fuera del top 10 no entra a la cola ese día,
     * porque su jornada termina en la última ronda regular.
     *
     * @param  Collection<int, RoundResult>|null  $results
     */
    private function nextRoundFor(CompetitionDay $day, ?Collection $results, ?CategoryStanding $standing): ?Round
    {
        $resultsByRound = $results?->keyBy('round_id') ?? collect();

        $active = $day->rounds->firstWhere('status', RoundStatus::Active);

        $candidate = $active ?? $day->rounds->first(
            fn (Round $round): bool => $round->status === RoundStatus::Pending && ! $resultsByRound->has($round->id)
        );

        if ($candidate === null) {
            return null;
        }

        if ($active !== null && $resultsByRound->has($active->id)) {
            return null;
        }

        if ($candidate->is_final && ! $this->qualifiesForFinal($candidate, $standing)) {
            return null;
        }

        return $candidate;
    }

    /**
     * La final se gana en la pista de los días previos: sin posición en la
     * clasificación no hay entrada, aunque la ronda siga abierta.
     */
    private function qualifiesForFinal(Round $round, ?CategoryStanding $standing): bool
    {
        if (! $round->is_final) {
            return true;
        }

        return $standing !== null
            && $standing->position !== null
            && $standing->position <= TournamentCategory::FINALISTS_LIMIT;
    }

    /**
     * @return array<string, mixed>
     */
    private function entryFor(
        Registration $registration,
        CompetitionDay $day,
        Round $round,
        ?CategoryStanding $standing
    ): array {
        $result = RoundResult::query()
            ->where('round_id', $round->id)
            ->where('registration_id', $registration->id)
            ->first();

        $category = $registration->category;
        $group = $registration->competitionGroup;

        return [
            'registration_id' => $registration->id,
            'registration_number' => $registration->registration_number,
            'category' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
            ],
            'day' => [
                'id' => $day->id,
                'day_number' => $day->day_number,
                'status' => $day->status->value,
                'status_label' => $day->status->label(),
            ],
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'is_final' => $round->is_final,
                'status' => $round->status->value,
                'status_label' => $round->status->label(),
                'accepts_results' => $round->acceptsResults(),
            ],
            'group' => $group === null ? null : [
                'id' => $group->id,
                'name' => $group->name,
                'order' => $group->order,
            ],
            'participant' => [
                'public_id' => $registration->participant->public_id,
                'full_name' => $registration->participant->full_name,
                'photo_url' => $registration->participant->photo_url,
            ],
            'horse' => $registration->horse?->name,
            'result' => $result === null ? null : [
                'id' => $result->id,
                'time_ms' => $result->time_ms,
                'time' => $result->time_formatted,
                'penalty_ms' => $result->penalty_ms,
                'penalty' => $result->penalty_formatted,
                'total' => Milliseconds::fromInt($result->countableTimeMs())->format(),
                'successful' => $result->successful,
                'notes' => $result->notes,
            ],
            'sort_active' => $round->status === RoundStatus::Active ? 0 : 1,
            'sort_group_order' => $group === null ? PHP_INT_MAX : $group->order,
            'sort_successful_rounds' => $standing === null ? 0 : $standing->total_successful_rounds,
            'sort_time_ms' => $standing === null ? 0 : $standing->total_time_ms,
            'sort_registration' => $registration->registration_number,
        ];
    }

    /**
     * Orden operativo del primer día: primero quien ya está en una ronda
     * abierta, luego el orden del grupo, después el dorsal.
     *
     * Desde el segundo día el orden es deportivo por categoría: salen primero
     * los que tienen menos lazos acertados y, entre quienes van iguales, los
     * que acumulan más tiempo registrado.
     *
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function compare(array $left, array $right, bool $standingsOrder): int
    {
        if ($standingsOrder) {
            return [
                $left['sort_active'],
                $left['category']['id'],
                $left['sort_successful_rounds'],
                -$left['sort_time_ms'],
                $left['sort_registration'],
                $left['registration_id'],
            ] <=> [
                $right['sort_active'],
                $right['category']['id'],
                $right['sort_successful_rounds'],
                -$right['sort_time_ms'],
                $right['sort_registration'],
                $right['registration_id'],
            ];
        }

        return [
            $left['sort_active'],
            $left['sort_group_order'],
            $left['sort_registration'],
            $left['registration_id'],
        ] <=> [
            $right['sort_active'],
            $right['sort_group_order'],
            $right['sort_registration'],
            $right['registration_id'],
        ];
    }

    /**
     * @param  list<string>  $statuses
     */
    private function summarizeStatus(array $statuses): string
    {
        if (in_array('active', $statuses, true)) {
            return 'active';
        }

        if (in_array('pending', $statuses, true)) {
            return 'pending';
        }

        if (in_array('cancelled', $statuses, true) && ! in_array('finished', $statuses, true)) {
            return 'cancelled';
        }

        return 'finished';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'En curso',
            'pending' => 'Pendiente',
            'cancelled' => 'Cancelado',
            default => 'Finalizado',
        };
    }
}
