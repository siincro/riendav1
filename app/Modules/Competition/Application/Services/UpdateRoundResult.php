<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Domain\Events\RoundResultUpdated;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Corrige un resultado ya registrado.
 *
 *   Antes: 8.342  →  Después: 7.842
 *
 * El resultado original no se pierde silenciosamente: el evento lleva el
 * estado anterior para que la auditoría deje trazabilidad, y el recálculo en
 * cascada (día → acumulado → clasificación) lo dispara ese mismo evento.
 */
final class UpdateRoundResult
{
    public function handle(RoundResult $result, RoundResultData $data): RoundResult
    {
        if ($data->successful && $data->time->isZero()) {
            throw new DomainException(
                'Un lazo acertado debe tener un tiempo mayor que cero.',
                ['time' => 'Ingrese el tiempo o marque el lazo como fallido.'],
            );
        }

        $previous = [
            'time_ms' => $result->time_ms,
            'successful' => $result->successful,
            'notes' => $result->notes,
        ];

        $updated = DB::transaction(function () use ($result, $data): RoundResult {
            $result->fill([
                ...$data->toAttributes(),
                'recorded_by' => $data->recordedBy ?? $result->recorded_by,
            ])->save();

            return $result->refresh();
        });

        RoundResultUpdated::dispatch($updated, $previous);

        return $updated;
    }
}
