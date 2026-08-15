<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class TournamentsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Tournaments';
    }
}
