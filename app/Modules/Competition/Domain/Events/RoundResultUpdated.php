<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Events;

use App\Modules\Competition\Domain\Events\Concerns\BroadcastsCategoryUpdates;
use App\Modules\Competition\Domain\Models\RoundResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * El administrador corrigió un resultado ya registrado.
 *
 * Lleva el estado anterior para que la auditoría pueda dejar trazabilidad
 * de qué cambió exactamente.
 */
final class RoundResultUpdated implements ShouldBroadcast
{
    use BroadcastsCategoryUpdates;
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array{time_ms: int, successful: bool, notes: string|null}  $previous
     */
    public function __construct(
        public readonly RoundResult $result,
        public readonly array $previous,
    ) {}

    public function model(): RoundResult
    {
        return $this->result;
    }

    public function broadcastAs(): string
    {
        return 'round-result.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'participant' => $this->result->registration->participant->public_id,
            'time_ms' => $this->result->time_ms,
            'successful' => $this->result->successful,
            'day_number' => $this->result->round->competitionDay->day_number,
            'round_number' => $this->result->round->round_number,
        ];
    }
}
