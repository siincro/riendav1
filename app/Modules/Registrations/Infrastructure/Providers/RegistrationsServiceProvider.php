<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class RegistrationsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Registrations';
    }
}
