<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Listeners;

use App\Modules\Audit\Application\Services\RecordAuditEntry;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Competition\Domain\Models\RoundResult;

/**
 * Traza toda escritura sobre resultados oficiales.
 *
 * El registro guarda el valor anterior y el nuevo para poder responder,
 * meses después, quién cambió un 8.342 por un 7.842 y cuándo.
 */
final class AuditResultChanges
{
    public function __construct(private readonly RecordAuditEntry $recordAuditEntry) {}

    public function handleRecorded(RoundResultRecorded $event): void
    {
        $result = $event->result;

        $this->recordAuditEntry->handle(
            action: 'round_result.recorded',
            entity: $result,
            newValue: $this->snapshot($result->time_ms, $result->successful, $result->notes),
            context: $this->context($event->result),
        );
    }

    public function handleUpdated(RoundResultUpdated $event): void
    {
        $result = $event->result;

        $this->recordAuditEntry->handle(
            action: 'round_result.updated',
            entity: $result,
            oldValue: $this->snapshot(
                (int) $event->previous['time_ms'],
                (bool) $event->previous['successful'],
                $event->previous['notes'] ?? null,
            ),
            newValue: $this->snapshot($result->time_ms, $result->successful, $result->notes),
            context: $this->context($result),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(int $timeMs, bool $successful, ?string $notes): array
    {
        return [
            'time_ms' => $timeMs,
            'time' => sprintf('%d.%03d', intdiv($timeMs, 1000), $timeMs % 1000),
            'successful' => $successful,
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(RoundResult $result): array
    {
        $result->loadMissing(['round.competitionDay', 'registration.participant']);

        return [
            'round_number' => $result->round->round_number,
            'day_number' => $result->round->competitionDay->day_number,
            'tournament_category_id' => $result->round->competitionDay->tournament_category_id,
            'participant' => $result->registration->participant?->public_id,
        ];
    }
}
