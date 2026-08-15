<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\ServiceProvider;

/**
 * Punto único de registro de los módulos de dominio de RIENDA.
 *
 * El orden importa: un módulo se registra después de aquellos de los que
 * dependen sus migraciones y llaves foráneas.
 */
class ModulesServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<ServiceProvider>>
     */
    protected array $providers = [
        Shared\Infrastructure\Providers\SharedServiceProvider::class,
        Participants\Infrastructure\Providers\ParticipantsServiceProvider::class,
        Horses\Infrastructure\Providers\HorsesServiceProvider::class,
        Tournaments\Infrastructure\Providers\TournamentsServiceProvider::class,
        Registrations\Infrastructure\Providers\RegistrationsServiceProvider::class,
        Competition\Infrastructure\Providers\CompetitionServiceProvider::class,
        Leaderboards\Infrastructure\Providers\LeaderboardsServiceProvider::class,
        Challenges\Infrastructure\Providers\ChallengesServiceProvider::class,
        Audit\Infrastructure\Providers\AuditServiceProvider::class,
        Public\Infrastructure\Providers\PublicServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }
    }
}
