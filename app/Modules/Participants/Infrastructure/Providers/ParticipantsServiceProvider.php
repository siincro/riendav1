<?php

declare(strict_types=1);

namespace App\Modules\Participants\Infrastructure\Providers;

use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;

class ParticipantsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Participants';
    }
}
