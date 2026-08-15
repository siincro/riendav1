<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Domain\Enums\CompetitionDayStatus;
use App\Modules\Competition\Domain\Enums\RoundStatus;
use App\Modules\Competition\Domain\Events\CompetitionDayCompleted;
use App\Modules\Competition\Domain\Events\CompetitionDayStarted;
use App\Modules\Competition\Domain\Events\RoundCompleted;
use App\Modules\Competition\Domain\Events\RoundStarted;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gobierna el avance de la competencia: iniciar y finalizar días y rondas.
 *
 * Al iniciar un día o una ronda, la categoría y el torneo pasan a "en
 * progreso" automáticamente: el estado de la competencia se deriva de lo que
 * está ocurriendo en la pista, no de que alguien recuerde marcarlo.
 */
final class ManageRoundLifecycle
{
    public function startDay(CompetitionDay $day): CompetitionDay
    {
        if ($day->status === CompetitionDayStatus::Finished) {
            throw new DomainException('Este día ya fue finalizado.');
        }

        $this->ensureHasCompetitors($day->category, 'iniciar el día');

        DB::transaction(function () use ($day): void {
            $day->status = CompetitionDayStatus::Active;
            $day->save();

            $this->markCategoryInProgress($day->category);
        });

        CompetitionDayStarted::dispatch($day);

        return $day->refresh();
    }

    public function startRound(Round $round): Round
    {
        if ($round->status === RoundStatus::Cancelled) {
            throw new DomainException('No se puede iniciar un lazo cancelado.');
        }

        $day = $round->competitionDay;

        if ($day->status === CompetitionDayStatus::Cancelled) {
            throw new DomainException('El día de competencia está cancelado.');
        }

        $this->ensureHasCompetitors($day->category, 'iniciar el lazo');

        DB::transaction(function () use ($round, $day): void {
            if ($day->status === CompetitionDayStatus::Pending) {
                $day->status = CompetitionDayStatus::Active;
                $day->save();
            }

            $round->status = RoundStatus::Active;
            $round->started_at ??= Carbon::now();
            $round->save();

            $this->markCategoryInProgress($day->category);
        });

        RoundStarted::dispatch($round);

        return $round->refresh();
    }

    /**
     * Arranca el mismo día en todas las categorías del torneo.
     *
     * La consola central puede poner en marcha la jornada completa de una
     * sola vez. Las categorías que ya tienen el día activo o finalizado se
     * omiten, y también las que no tienen vaqueros inscritos: no se puede
     * iniciar una competencia vacía.
     *
     * @return int Cantidad de categorías cuyo día quedó en marcha.
     */
    public function startDayForAllCategories(Tournament $tournament, int $dayNumber): int
    {
        $started = 0;

        foreach ($tournament->categories()->with('competitionDays')->get() as $category) {
            $day = $category->competitionDays->firstWhere('day_number', $dayNumber);

            if ($day === null || $day->status === CompetitionDayStatus::Finished) {
                continue;
            }

            if (! $category->registrations()->active()->exists()) {
                continue;
            }

            $this->startDay($day);
            $started++;
        }

        return $started;
    }

    /**
     * Cierra la ronda. No exige que todos los inscritos tengan resultado —
     * un jinete puede no presentarse — pero deja constancia del cierre.
     */
    public function completeRound(Round $round): Round
    {
        if ($round->status === RoundStatus::Cancelled) {
            throw new DomainException('No se puede finalizar un lazo cancelado.');
        }

        $round->status = RoundStatus::Finished;
        $round->finished_at ??= Carbon::now();
        $round->save();

        RoundCompleted::dispatch($round);

        return $round->refresh();
    }

    /**
     * Cierra la ronda en cuanto todos los que deben correrla registraron
     * resultado. Devuelve true si quedó finalizada.
     *
     * En las rondas regulares "todos" son los inscritos activos de la
     * categoría; en el lazo final, los clasificados (top 10).
     *
     * Es lo que hace que la cola de la consola central avance sola: cuando el
     * último vaquero de la cola registra, el lazo termina y la siguiente ronda
     * pendiente pasa a ser la activa.
     */
    public function completeRoundIfEveryoneRecorded(Round $round): bool
    {
        if ($round->status !== RoundStatus::Active) {
            return false;
        }

        $category = $round->competitionDay->category;

        $expectedIds = $round->is_final
            ? $category->finalistRegistrationIds()
            : $category->registrations()->active()->pluck('id');

        if ($expectedIds->isEmpty()) {
            return false;
        }

        $recorded = RoundResult::query()
            ->where('round_id', $round->id)
            ->whereIn('registration_id', $expectedIds)
            ->count();

        if ($recorded < $expectedIds->count()) {
            return false;
        }

        $this->completeRound($round);

        return true;
    }

    /**
     * Cierra el día. Todas sus rondas pendientes quedan finalizadas para que
     * el acumulado del día no quede en un estado ambiguo.
     */
    public function completeDay(CompetitionDay $day): CompetitionDay
    {
        DB::transaction(function () use ($day): void {
            $day->rounds()
                ->whereIn('status', [RoundStatus::Pending->value, RoundStatus::Active->value])
                ->update([
                    'status' => RoundStatus::Finished->value,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);

            $day->status = CompetitionDayStatus::Finished;
            $day->save();
        });

        CompetitionDayCompleted::dispatch($day);

        return $day->refresh();
    }

    /**
     * Finaliza la categoría. Su clasificación queda congelada tal como está
     * en ese momento, calculada a partir de los resultados registrados.
     */
    public function completeCategory(TournamentCategory $category): TournamentCategory
    {
        if ($category->status === CategoryStatus::Cancelled) {
            throw new DomainException('No se puede finalizar una categoría cancelada.');
        }

        DB::transaction(function () use ($category): void {
            $category->competitionDays()
                ->whereIn('status', [CompetitionDayStatus::Pending->value, CompetitionDayStatus::Active->value])
                ->each(fn (CompetitionDay $day) => $this->completeDay($day));

            $category->status = CategoryStatus::Finished;
            $category->save();
        });

        return $category->refresh();
    }

    /**
     * Sin jinetes activos no hay competencia que arrancar.
     *
     * No es solo que no habría a quién cronometrar: iniciar mueve la categoría
     * y el torneo a «en progreso», y eso congela la estructura de días y
     * rondas. Arrancar una categoría vacía por descuido la dejaría bloqueada.
     */
    private function ensureHasCompetitors(TournamentCategory $category, string $action): void
    {
        if ($category->registrations()->active()->exists()) {
            return;
        }

        throw new DomainException(
            "La categoría {$category->name} no tiene vaqueros inscritos: no se puede {$action}."
        );
    }

    private function markCategoryInProgress(TournamentCategory $category): void
    {
        if ($category->status === CategoryStatus::Pending) {
            $category->status = CategoryStatus::InProgress;
            $category->save();
        }

        $tournament = $category->tournament;

        if ($tournament !== null && $tournament->status === TournamentStatus::Published) {
            $tournament->status = TournamentStatus::InProgress;
            $tournament->save();
        }
    }
}
