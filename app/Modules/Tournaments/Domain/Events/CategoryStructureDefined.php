<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Domain\Events;

use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * La estructura competitiva de una categoría quedó definida o redefinida.
 *
 * El módulo de Competencia escucha este evento para materializar los días y
 * las rondas. Tournaments no conoce a Competition: solo anuncia el hecho.
 */
final class CategoryStructureDefined
{
    use Dispatchable;

    public function __construct(public readonly TournamentCategory $category) {}
}
