<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Application\Services;

use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Tournaments\Domain\Events\CategoryStructureDefined;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea o actualiza una categoría junto con su estructura de días y rondas.
 *
 * Cambiar `number_of_days` o `rounds_per_day` reconstruye el calendario, así
 * que solo se permite mientras la categoría no haya empezado a competirse:
 * de otro modo se invalidarían resultados ya registrados.
 */
final class SaveTournamentCategory
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Tournament $tournament, array $attributes): TournamentCategory
    {
        if ($tournament->isClosed()) {
            throw new DomainException('No se pueden agregar categorías a un evento finalizado o cancelado.');
        }

        return DB::transaction(function () use ($tournament, $attributes): TournamentCategory {
            $category = $tournament->categories()->create($this->normalize($attributes));

            CategoryStructureDefined::dispatch($category);

            return $category->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TournamentCategory $category, array $attributes): TournamentCategory
    {
        $normalized = $this->normalize($attributes);

        $structureChanged = $category->number_of_days !== (int) $normalized['number_of_days']
            || $category->rounds_per_day !== (int) $normalized['rounds_per_day'];

        if ($structureChanged && ! $category->structureIsEditable()) {
            throw new DomainException(
                'La estructura de días y lazos no se puede modificar una vez iniciada la categoría.',
                ['number_of_days' => 'La categoría ya está en progreso o finalizada.'],
            );
        }

        return DB::transaction(function () use ($category, $normalized, $structureChanged): TournamentCategory {
            $category->fill($normalized)->save();

            if ($structureChanged) {
                CategoryStructureDefined::dispatch($category);
            }

            return $category->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'slug' => Str::slug($attributes['slug'] ?? $attributes['name']),
            'description' => $attributes['description'] ?? null,
            'order' => (int) ($attributes['order'] ?? 0),
            'number_of_days' => (int) $attributes['number_of_days'],
            'rounds_per_day' => (int) $attributes['rounds_per_day'],
        ];
    }
}
