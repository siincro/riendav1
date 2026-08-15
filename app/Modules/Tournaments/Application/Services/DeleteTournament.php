<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Application\Services;

use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Support\Facades\Storage;

/**
 * Borra un torneo que todavía no tiene jinetes inscritos.
 *
 * Es la única forma de deshacer un torneo creado por error. Con inscritos de
 * por medio el borrado deja de estar disponible: ahí el camino es cancelarlo,
 * que conserva el rastro de lo que pasó.
 *
 * Las categorías, sus días y sus rondas se van con él por las llaves foráneas
 * en cascada; la portada, que vive fuera de la base, se borra a mano.
 */
final class DeleteTournament
{
    public function handle(Tournament $tournament): void
    {
        if (! $tournament->canBeDeleted()) {
            throw new DomainException(
                "El evento {$tournament->name} tiene vaqueros inscritos: no se puede eliminar. Cancélelo si no se va a correr."
            );
        }

        $cover = $tournament->cover_image_path;

        $tournament->delete();

        if ($cover !== null) {
            Storage::disk('public')->delete($cover);
        }
    }
}
