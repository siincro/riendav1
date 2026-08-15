<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Application\Services;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Leaderboards\Domain\Models\DailyResult;
use App\Modules\Registrations\Domain\Models\Registration;
use Illuminate\Support\Facades\DB;

/**
 * Calcula el resultado de un inscrito en un día de competencia.
 *
 *   daily_time_ms     = SUM(time_ms + penalty_ms) de los lazos acertados
 *   daily_penalty_ms  = cuánto de ese tiempo es castigo
 *   successful_rounds = COUNT de las rondas acertadas
 *
 * El tiempo de una ronda fallida nunca se acumula, aunque el registro exista.
 */
final class CalculateDailyResult
{
    public function handle(CompetitionDay $day, Registration $registration): DailyResult
    {
        // Se consulta con el query builder, no con Eloquent: lo que interesa
        // son agregados, no modelos.
        $totals = DB::table('round_results')
            ->whereIn('round_id', fn ($query) => $query
                ->select('id')
                ->from('rounds')
                ->where('competition_day_id', $day->id)
            )
            ->where('registration_id', $registration->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN successful = 1 THEN time_ms + penalty_ms ELSE 0 END), 0) as time_ms')
            ->selectRaw('COALESCE(SUM(CASE WHEN successful = 1 THEN penalty_ms ELSE 0 END), 0) as penalty_ms')
            ->selectRaw('COALESCE(SUM(CASE WHEN successful = 1 THEN 1 ELSE 0 END), 0) as successful_rounds')
            ->selectRaw('COUNT(*) as recorded_rounds')
            ->first();

        return DailyResult::query()->updateOrCreate(
            [
                'competition_day_id' => $day->id,
                'registration_id' => $registration->id,
            ],
            [
                'time_ms' => (int) ($totals->time_ms ?? 0),
                'penalty_ms' => (int) ($totals->penalty_ms ?? 0),
                'successful_rounds' => (int) ($totals->successful_rounds ?? 0),
                'recorded_rounds' => (int) ($totals->recorded_rounds ?? 0),
            ]
        );
    }

    /**
     * Recalcula el día completo para todos los inscritos de la categoría.
     *
     * @return int Cantidad de inscritos recalculados.
     */
    public function forAllRegistrations(CompetitionDay $day): int
    {
        $registrations = Registration::query()
            ->where('tournament_category_id', $day->tournament_category_id)
            ->get();

        $registrations->each(fn (Registration $registration) => $this->handle($day, $registration));

        return $registrations->count();
    }
}
