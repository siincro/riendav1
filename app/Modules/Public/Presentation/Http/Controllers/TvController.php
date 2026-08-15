<?php

declare(strict_types=1);

namespace App\Modules\Public\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leaderboards\Presentation\Http\Resources\PublicStandingResource;
use App\Modules\Public\Application\Services\BuildTvOverlay;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * RIENDA TV: el overlay que se conecta a OBS Studio como fuente de navegador.
 *
 * Dos URLs renderizan la misma página de solo lectura: `/tv` sigue la
 * categoría activa del torneo en curso y `/tv/{torneo}/{categoria}` fija una
 * categoría concreta (para repeticiones o para selección manual del operador).
 * La actualización en vivo llega por el canal público de Reverb, sin pedir
 * nada más que los datos frescos por HTTP.
 */
class TvController extends Controller
{
    public function index(BuildTvOverlay $overlay): Response
    {
        return $this->render($overlay, $overlay->activeCategory());
    }

    public function show(
        Tournament $tournament,
        TournamentCategory $category,
        BuildTvOverlay $overlay
    ): Response {
        if (! $tournament->status->isPubliclyVisible()) {
            throw new NotFoundHttpException;
        }

        return $this->render($overlay, $category);
    }

    private function render(BuildTvOverlay $overlay, ?TournamentCategory $category): Response
    {
        $data = $category === null ? null : $overlay->handle($category);

        return Inertia::render('public/tv/Overlay', [
            'tournament' => $data['tournament'] ?? null,
            'category' => $data['category'] ?? null,
            'standings' => PublicStandingResource::collection($data['standings'] ?? []),
        ]);
    }
}
