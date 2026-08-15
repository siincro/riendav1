<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Application\Services;

use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Models\Registration;
use Illuminate\Support\Facades\DB;

/**
 * Acumula los resultados diarios de un inscrito en su total de categoría.
 *
 *   total_time_ms           = SUM(daily_time_ms), castigos incluidos
 *   total_penalty_ms        = SUM(daily_penalty_ms)
 *   total_successful_rounds = SUM(daily_successful_rounds)
 *
 * Deja el acumulado guardado sin posición: la posición depende de todos los
 * demás inscritos y la asigna CalculateLeaderboard.
 */
final class CalculateTournamentResult
{
    public function handle(Registration $registration): CategoryStanding
    {
        $totals = DB::table('daily_results')
            ->where('registration_id', $registration->id)
            ->selectRaw('COALESCE(SUM(time_ms), 0) as total_time_ms')
            ->selectRaw('COALESCE(SUM(penalty_ms), 0) as total_penalty_ms')
            ->selectRaw('COALESCE(SUM(successful_rounds), 0) as total_successful_rounds')
            ->selectRaw('COALESCE(SUM(recorded_rounds), 0) as recorded_rounds')
            ->first();

        return CategoryStanding::query()->updateOrCreate(
            [
                'tournament_category_id' => $registration->tournament_category_id,
                'registration_id' => $registration->id,
            ],
            [
                'total_time_ms' => (int) ($totals->total_time_ms ?? 0),
                'total_penalty_ms' => (int) ($totals->total_penalty_ms ?? 0),
                'total_successful_rounds' => (int) ($totals->total_successful_rounds ?? 0),
                'recorded_rounds' => (int) ($totals->recorded_rounds ?? 0),
            ]
        );
    }
}
