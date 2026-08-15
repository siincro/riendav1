<?php

declare(strict_types=1);

namespace App\Modules\Horses\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class HorsesServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Horses';
    }
}
