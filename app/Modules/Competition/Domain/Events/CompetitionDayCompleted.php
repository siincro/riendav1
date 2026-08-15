<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Events;

use App\Modules\Competition\Domain\Events\Concerns\BroadcastsCategoryUpdates;
use App\Modules\Competition\Domain\Models\CompetitionDay;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class CompetitionDayCompleted implements ShouldBroadcast
{
    use BroadcastsCategoryUpdates;
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly CompetitionDay $day) {}

    public function model(): CompetitionDay
    {
        return $this->day;
    }

    public function broadcastAs(): string
    {
        return 'competition-day.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'day_number' => $this->day->day_number,
        ];
    }
}
