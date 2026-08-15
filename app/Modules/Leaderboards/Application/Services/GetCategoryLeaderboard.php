<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Application\Services;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Collection;

/**
 * Reúne la clasificación de una categoría para su exhibición pública.
 *
 * Devuelve la tabla de posiciones (el dato derivado ya calculado) junto con
 * el detalle día a día de cada inscrito, de modo que el público vea tanto la
 * clasificación acumulada como la clasificación diaria.
 */
final class GetCategoryLeaderboard
{
    /**
     * @return array{
     *     standings: Collection<int, CategoryStanding>,
     *     days: array<int, array<string, mixed>>,
     *     rows: Collection<int, array<string, mixed>>,
     * }
     */
    public function handle(TournamentCategory $category): array
    {
        $standings = $category->standings()
            ->with(['registration.participant', 'registration.horse'])
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderByDesc('total_successful_rounds')
            ->orderBy('total_time_ms')
            ->get();

        $days = $category->competitionDays()
            ->with([
                'rounds' => fn ($query) => $query->orderBy('round_number'),
                'dailyResults.registration.participant',
                'dailyResults.registration.horse',
            ])
            ->get();

        $rows = $category->registrations()
            ->with(['participant', 'horse'])
            ->orderBy('registration_number')
            ->get()
            ->map(fn (Registration $registration) => $this->rowFor($registration, $days));

        return [
            'standings' => $standings,
            'days' => $this->shapeDays($days),
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, CompetitionDay>  $days
     * @return array<int, array<string, mixed>>
     */
    private function shapeDays(Collection $days): array
    {
        return $days
            ->map(fn (CompetitionDay $day): array => [
                'day_number' => $day->day_number,
                'date' => $day->date?->toDateString(),
                'status' => $day->status->value,
                'status_label' => $day->status->label(),
                'rounds' => $day->rounds
                    ->map(fn (Round $round): array => [
                        'round_number' => $round->round_number,
                        'is_final' => $round->is_final,
                        'status' => $round->status->value,
                        'status_label' => $round->status->label(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Una fila del cuadro diario: el inscrito y su resultado por cada día.
     *
     * @param  Collection<int, CompetitionDay>  $days
     * @return array<string, mixed>
     */
    private function rowFor(Registration $registration, Collection $days): array
    {
        return [
            'registration_number' => $registration->registration_number,
            'participant' => [
                'public_id' => $registration->participant->public_id,
                'full_name' => $registration->participant->full_name,
            ],
            'horse' => $registration->horse?->name,
            'per_day' => $days
                ->map(function (CompetitionDay $day) use ($registration): ?array {
                    $daily = $day->dailyResults->firstWhere('registration_id', $registration->id);

                    if ($daily === null) {
                        return null;
                    }

                    return [
                        'time_ms' => $daily->time_ms,
                        'time_formatted' => $daily->time_formatted,
                        // El tiempo ya trae el castigo dentro: este es el
                        // desglose para poder explicarlo.
                        'penalty_ms' => $daily->penalty_ms,
                        'penalty_formatted' => $daily->penalty_formatted,
                        'successful_rounds' => $daily->successful_rounds,
                        'recorded_rounds' => $daily->recorded_rounds,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
