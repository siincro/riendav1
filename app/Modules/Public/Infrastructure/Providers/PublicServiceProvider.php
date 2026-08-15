<?php

declare(strict_types=1);

namespace App\Modules\Public\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class PublicServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Public';
    }
}
