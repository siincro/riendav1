<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Events;

use App\Modules\Competition\Domain\Events\Concerns\BroadcastsCategoryUpdates;
use App\Modules\Competition\Domain\Models\Round;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class RoundStarted implements ShouldBroadcast
{
    use BroadcastsCategoryUpdates;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly Round $round) {}

    public function model(): Round
    {
        return $this->round;
    }

    public function broadcastAs(): string
    {
        return 'round.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'day_number' => $this->round->competitionDay->day_number,
            'round_number' => $this->round->round_number,
        ];
    }
}
