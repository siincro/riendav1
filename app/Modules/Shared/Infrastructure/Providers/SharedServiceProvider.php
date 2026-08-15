<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Kernel compartido: convenciones que aplican a todos los módulos.
 */
class SharedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing($this->resolveFactoryName(...));
    }

    /**
     * Traduce el modelo de un módulo a su factory dentro del mismo módulo.
     *
     * App\Modules\Participants\Domain\Models\Participant
     *   => App\Modules\Participants\Infrastructure\Database\Factories\ParticipantFactory
     *
     * @param  class-string<Model>  $modelName
     * @return class-string<Factory<Model>>
     */
    protected function resolveFactoryName(string $modelName): string
    {
        foreach ($this->candidatesFor($modelName) as $candidate) {
            if (is_subclass_of($candidate, Factory::class)) {
                return $candidate;
            }
        }

        // Fallar aquí con el nombre del modelo es más útil que dejar que
        // Laravel reporte una clase inexistente más adelante.
        throw new InvalidArgumentException(
            "No se encontró una factory para el modelo [{$modelName}]."
        );
    }

    /**
     * Nombres posibles de la factory, del más específico al convencional.
     *
     * @return list<string>
     */
    protected function candidatesFor(string $modelName): array
    {
        $candidates = [];

        if (str_starts_with($modelName, 'App\\Modules\\')) {
            $candidates[] = str_replace(
                '\\Domain\\Models\\',
                '\\Infrastructure\\Database\\Factories\\',
                $modelName
            ).'Factory';
        }

        $candidates[] = 'Database\\Factories\\'.class_basename($modelName).'Factory';

        return $candidates;
    }
}
