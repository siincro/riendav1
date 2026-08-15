<?php

declare(strict_types=1);

namespace App\Modules\Leaderboards\Application\Services;

use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Registrations\Domain\Enums\RegistrationStatus;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ordena la clasificación de una categoría y asigna posiciones.
 *
 * El criterio del deporte, en este orden:
 *
 *   1. Mayor cantidad de rondas acertadas   (successful_rounds DESC)
 *   2. Menor tiempo acumulado               (total_time_ms ASC)
 *
 * Es deliberado que las rondas acertadas manden sobre el tiempo: un jinete
 * con 9 aciertos y menos tiempo queda por debajo de uno con 10 aciertos.
 *
 * Cuando ambos valores coinciden, la posición se comparte (1, 2, 2, 4). No se
 * inventa un tercer criterio de desempate mientras las reglas oficiales del
 * deporte no lo definan.
 */
final class CalculateLeaderboard
{
    /**
     * @return Collection<int, CategoryStanding>
     */
    public function handle(TournamentCategory $category): Collection
    {
        return DB::transaction(function () use ($category): Collection {
            $standings = CategoryStanding::query()
                ->where('tournament_category_id', $category->id)
                ->whereHas(
                    'registration',
                    fn ($query) => $query->where('status', RegistrationStatus::Active)
                )
                ->orderByDesc('total_successful_rounds')
                ->orderBy('total_time_ms')
                ->orderBy('registration_id')
                ->get();

            $this->assignPositions($standings);

            // Las inscripciones retiradas o descalificadas conservan su
            // acumulado pero salen de la tabla de posiciones.
            CategoryStanding::query()
                ->where('tournament_category_id', $category->id)
                ->whereNotIn('id', $standings->pluck('id')->all())
                ->update(['position' => null, 'tied' => false]);

            return $standings;
        });
    }

    /**
     * Posición con empates compartidos: la siguiente posición salta tantos
     * puestos como empatados haya (1, 2, 2, 4).
     *
     * @param  Collection<int, CategoryStanding>  $standings
     */
    private function assignPositions(Collection $standings): void
    {
        $position = 0;
        $index = 0;
        $previousKey = null;

        /** @var array<int, list<int>> $byKey */
        $byKey = [];

        foreach ($standings as $standing) {
            $index++;
            $key = $standing->total_successful_rounds.':'.$standing->total_time_ms;

            if ($key !== $previousKey) {
                $position = $index;
                $previousKey = $key;
            }

            $standing->position = $position;
            $byKey[$position][] = $standing->id;
            $standing->save();
        }

        // Marca como empatados los que comparten posición con alguien más.
        foreach ($byKey as $sharedPosition => $ids) {
            $tied = count($ids) > 1;

            CategoryStanding::query()
                ->whereIn('id', $ids)
                ->update(['tied' => $tied]);

            $standings
                ->whereIn('id', $ids)
                ->each(function (CategoryStanding $standing) use ($tied, $sharedPosition): void {
                    $standing->tied = $tied;
                    $standing->position = $sharedPosition;
                    $standing->syncOriginal();
                });
        }
    }
}
