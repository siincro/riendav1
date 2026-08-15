<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base de todo módulo de RIENDA.
 *
 * Un módulo es autocontenido: sus migraciones, factories y rutas viven dentro
 * de su propia carpeta y este proveedor las descubre por convención, de modo
 * que agregar un módulo nuevo no obliga a tocar archivos globales más allá del
 * registro en `ModulesServiceProvider`.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Nombre del módulo tal como aparece en `app/Modules/{name}`.
     */
    abstract public function name(): string;

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerAdminRoutes();
        $this->registerPublicRoutes();
        $this->registerBindings();
    }

    protected function registerMigrations(): void
    {
        $path = $this->modulePath('Infrastructure/Database/Migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    /**
     * Rutas del panel administrativo del módulo.
     *
     * Todas comparten prefijo `/admin`, nombre `admin.` y exigen sesión
     * autenticada y verificada — la regla del documento: la interfaz pública
     * es solamente de lectura.
     */
    protected function registerAdminRoutes(): void
    {
        $path = $this->modulePath('Presentation/Routes/admin.php');

        if (! is_file($path)) {
            return;
        }

        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('admin')
            ->name('admin.')
            ->group($path);
    }

    /**
     * Rutas públicas del módulo (la cara que ve el público).
     *
     * Viven en `Presentation/Routes/public.php`, comparten el grupo `web`
     * (necesario para Inertia y sesión) pero no exigen autenticación: la
     * interfaz pública es de solo lectura.
     */
    protected function registerPublicRoutes(): void
    {
        $path = $this->modulePath('Presentation/Routes/public.php');

        if (! is_file($path)) {
            return;
        }

        Route::middleware(['web'])
            ->name('public.')
            ->group($path);
    }

    /**
     * Punto de extensión para enlazar contratos del módulo en el contenedor.
     */
    protected function registerBindings(): void
    {
        //
    }

    protected function modulePath(string $relative = ''): string
    {
        return app_path('Modules/'.$this->name().($relative === '' ? '' : '/'.$relative));
    }
}
