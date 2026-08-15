<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;

/**
 * Materializa la estructura de una categoría: días × rondas por día, más el
 * lazo final del último día.
 *
 *   Días: 3, Rondas por día: 4
 *   => Día 1 (R1..R4), Día 2 (R1..R4), Día 3 (R1..R4 + Final)
 *
 * La final es una ronda extra (round_number = rounds_per_day + 1) en el último
 * día: solo la corre el top 10 de la clasificación y su resultado sí suma al
 * acumulado.
 *
 * Es idempotente: reconstruye solo lo que falta y ajusta los sobrantes, de
 * modo que reconfigurar una categoría no duplica días ni rondas.
 */
final class BuildCategorySchedule
{
    public function handle(TournamentCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $this->guardNoResultsWouldBeLost($category);

            $this->syncDays($category);

            $isLastDay = fn (CompetitionDay $day): bool => $day->day_number === $category->number_of_days;

            $category->competitionDays()->get()->each(
                fn (CompetitionDay $day) => $this->syncRounds($day, $category->rounds_per_day, $isLastDay($day))
            );
        });
    }

    /**
     * Reducir la estructura solo es válido si las rondas que desaparecen
     * están vacías: los datos originales de cada ronda no se descartan.
     */
    private function guardNoResultsWouldBeLost(TournamentCategory $category): void
    {
        $hasResultsBeyondStructure = CompetitionDay::query()
            ->where('tournament_category_id', $category->id)
            ->where(function ($query) use ($category): void {
                $query->where('day_number', '>', $category->number_of_days)
                    ->orWhereHas('rounds', fn ($rounds) => $rounds->where('round_number', '>', $category->rounds_per_day)
                        ->whereHas('results')
                    );
            })
            ->whereHas('rounds.results')
            ->exists();

        if ($hasResultsBeyondStructure) {
            throw new DomainException(
                'No se puede reducir la estructura: hay lazos con resultados registrados fuera de la nueva configuración.',
                ['number_of_days' => 'Existen resultados en los días o lazos que se eliminarían.'],
            );
        }
    }

    private function syncDays(TournamentCategory $category): void
    {
        for ($dayNumber = 1; $dayNumber <= $category->number_of_days; $dayNumber++) {
            CompetitionDay::query()->firstOrCreate(
                [
                    'tournament_category_id' => $category->id,
                    'day_number' => $dayNumber,
                ],
                [
                    'date' => $this->estimateDate($category, $dayNumber),
                    'status' => CompetitionDayStatus::Pending,
                ]
            );
        }

        CompetitionDay::query()
            ->where('tournament_category_id', $category->id)
            ->where('day_number', '>', $category->number_of_days)
            ->delete();
    }

    private function syncRounds(CompetitionDay $day, int $roundsPerDay, bool $isLastDay): void
    {
        for ($roundNumber = 1; $roundNumber <= $roundsPerDay; $roundNumber++) {
            Round::query()->firstOrCreate(
                [
                    'competition_day_id' => $day->id,
                    'round_number' => $roundNumber,
                ],
                [
                    'is_final' => false,
                    'status' => RoundStatus::Pending,
                ]
            );
        }

        if ($isLastDay) {
            $finalNumber = $roundsPerDay + 1;

            Round::query()->firstOrCreate(
                [
                    'competition_day_id' => $day->id,
                    'round_number' => $finalNumber,
                ],
                [
                    'is_final' => true,
                    'status' => RoundStatus::Pending,
                ]
            );
        }

        // Normaliza las marcas al guardar: las rondas regulares nunca pueden
        // quedar marcadas como final, y la final siempre es la ronda extra del
        // último día.
        Round::query()
            ->where('competition_day_id', $day->id)
            ->where('round_number', '<=', $roundsPerDay)
            ->where('is_final', true)
            ->update(['is_final' => false, 'updated_at' => now()]);

        if ($isLastDay) {
            Round::query()
                ->where('competition_day_id', $day->id)
                ->where('round_number', $roundsPerDay + 1)
                ->where('is_final', false)
                ->update(['is_final' => true, 'updated_at' => now()]);
        }

        // Las rondas fuera de la configuración desaparecen (entre ellas la
        // final cuando la categoría perdió el último día o las rondas por día).
        Round::query()
            ->where('competition_day_id', $day->id)
            ->where('round_number', '>', $roundsPerDay + ($isLastDay ? 1 : 0))
            ->delete();
    }

    /**
     * Fecha tentativa del día, derivada del inicio del torneo. El
     * administrador puede ajustarla después día por día.
     */
    private function estimateDate(TournamentCategory $category, int $dayNumber): ?string
    {
        $start = $category->tournament?->start_date;

        return $start?->copy()->addDays($dayNumber - 1)->toDateString();
    }
}
