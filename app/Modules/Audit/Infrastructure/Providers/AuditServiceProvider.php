<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Providers;

use App\Modules\Audit\Application\Listeners\AuditResultChanges;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Shared\Infrastructure\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class AuditServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Audit';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(RoundResultRecorded::class, [AuditResultChanges::class, 'handleRecorded']);
        Event::listen(RoundResultUpdated::class, [AuditResultChanges::class, 'handleUpdated']);
    }
}
