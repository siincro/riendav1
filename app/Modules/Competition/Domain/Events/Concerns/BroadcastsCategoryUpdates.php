<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Events\Concerns;

use App\Modules\Competition\Domain\Models\CompetitionDay;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Lleva los eventos de competencia a Reverb.
 *
 * Cada evento se difunde en dos canales:
 *
 *   private-category.{id}            — consola del administrador (autorizado).
 *   public-category.{torneo}.{cat}   — landing «en vivo» y RIENDA TV, con
 *                                      identificadores públicos en el nombre
 *                                      del canal.
 *
 * El payload que se difunde es sanitizado (sin ids internos ni datos
 * personales): las vistas suscritas recargan su información del servidor.
 */
trait BroadcastsCategoryUpdates
{
    /**
     * @return array{id: int, slug: string, tournament_id: int, tournament_slug: string}
     */
    protected function categoryContext(): array
    {
        $category = match (true) {
            $this->model() instanceof Round => $this->model()->competitionDay->category,
            $this->model() instanceof CompetitionDay => $this->model()->category,
            $this->model() instanceof RoundResult => $this->model()->round->competitionDay->category,
            default => throw new \LogicException('Evento sin categoría difundible.'),
        };

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'tournament_id' => $category->tournament_id,
            'tournament_slug' => $category->tournament->slug,
        ];
    }

    /**
     * @return array{0: PrivateChannel, 1: PrivateChannel, 2: Channel}
     */
    public function broadcastOn(): array
    {
        $category = $this->categoryContext();

        return [
            new PrivateChannel("category.{$category['id']}"),
            new PrivateChannel("tournament.{$category['tournament_id']}"),
            new Channel("public-category.{$category['tournament_slug']}.{$category['slug']}"),
        ];
    }
}
