<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Listeners;

use App\Modules\Competition\Application\Services\ManageRoundLifecycle;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;

/**
 * Cuando el último vaquero de la cola registra su resultado, el lazo se cierra
 * solo: la siguiente ronda pendiente pasa a ser la activa y la cola avanza.
 */
final class CompleteRoundWhenEveryoneRecorded
{
    public function __construct(private readonly ManageRoundLifecycle $lifecycle) {}

    public function handle(RoundResultRecorded $event): void
    {
        $round = $event->result->round;

        if ($round === null) {
            return;
        }

        $this->lifecycle->completeRoundIfEveryoneRecorded($round);
    }
}
