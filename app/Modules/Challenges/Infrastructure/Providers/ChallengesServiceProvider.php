<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class ChallengesServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Challenges';
    }
}
