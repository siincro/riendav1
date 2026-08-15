<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Domain\Models\CompetitionGroup;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Models\Tournament;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Crea o actualiza un grupo de salida del torneo.
 *
 * El nombre es único por evento y el orden es manual: son decisiones de pista,
 * no algo que el sistema deba inventar.
 */
final class SaveCompetitionGroup
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tournament $tournament, array $attributes): CompetitionGroup
    {
        try {
            return $tournament->competitionGroups()->create($this->normalize($attributes));
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(
                'Ya existe un grupo con ese nombre en este evento.',
                ['name' => 'El nombre del grupo debe ser único dentro del evento.'],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompetitionGroup $group, array $attributes): CompetitionGroup
    {
        try {
            $group->fill($this->normalize($attributes))->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(
                'Ya existe un grupo con ese nombre en este evento.',
                ['name' => 'El nombre del grupo debe ser único dentro del evento.'],
            );
        }

        return $group->refresh();
    }

    public function delete(CompetitionGroup $group): void
    {
        // Las inscripciones conservan su historial: al borrar el grupo solo
        // pierden la asignación operativa de salida.
        $group->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        return [
            'name' => trim((string) $attributes['name']),
            'order' => (int) ($attributes['order'] ?? 0),
        ];
    }
}
