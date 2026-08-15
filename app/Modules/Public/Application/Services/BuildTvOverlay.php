<?php

declare(strict_types=1);

namespace App\Modules\Public\Application\Services;

use App\Modules\Leaderboards\Domain\Models\CategoryStanding;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Collection;

/**
 * Arma los datos del overlay de RIENDA TV.
 *
 * RIENDA TV es la pantalla que se conecta a OBS Studio como fuente de
 * navegador: muestra la tabla de clasificación de una categoría, de solo
 * lectura, y se mantiene al día por el canal público de Reverb.
 */
final class BuildTvOverlay
{
    /**
     * La categoría que el hub `/tv` sigue automáticamente: la primera
     * categoría con inscritos del torneo más reciente en progreso. La misma
     * que la landing considera "en vivo".
     */
    public function activeCategory(): ?TournamentCategory
    {
        return Tournament::query()
            ->where('status', TournamentStatus::InProgress)
            ->orderByDesc('id')
            ->first()
            ?->categories()
            ->withCount('registrations')
            ->first();
    }

    /**
     * @return array{
     *     tournament: array{name: string, slug: string},
     *     category: array{slug: string, name: string},
     *     standings: Collection<int, CategoryStanding>,
     * }
     */
    public function handle(TournamentCategory $category): array
    {
        return [
            'tournament' => [
                'name' => $category->tournament->name,
                'slug' => $category->tournament->slug,
            ],
            'category' => [
                'slug' => $category->slug,
                'name' => $category->name,
            ],
            'standings' => $this->standings($category),
        ];
    }

    /**
     * @return Collection<int, CategoryStanding>
     */
    private function standings(TournamentCategory $category): Collection
    {
        return $category->standings()
            ->with(['registration.participant', 'registration.horse'])
            ->orderByRaw('position is null')
            ->orderBy('position')
            ->orderByDesc('total_successful_rounds')
            ->orderBy('total_time_ms')
            ->get();
    }
}
