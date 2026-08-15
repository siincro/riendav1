<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Events;

use App\Modules\Competition\Domain\Events\Concerns\BroadcastsCategoryUpdates;
use App\Modules\Competition\Domain\Models\RoundResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se registró por primera vez el tiempo de un inscrito en una ronda.
 */
final class RoundResultRecorded implements ShouldBroadcast
{
    use BroadcastsCategoryUpdates;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly RoundResult $result) {}

    public function model(): RoundResult
    {
        return $this->result;
    }

    public function broadcastAs(): string
    {
        return 'round-result.recorded';
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
