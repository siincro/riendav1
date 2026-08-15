<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Services;

use App\Modules\Competition\Application\Data\RoundResultData;
use App\Modules\Competition\Domain\Events\RoundResultRecorded;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Competition\Domain\Models\RoundResult;
use App\Modules\Registrations\Domain\Models\Registration;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registra el tiempo de un inscrito en una ronda.
 *
 *   Validar resultado → Persistir → Anunciar el hecho
 *
 * El recálculo de resultados diarios, acumulados y clasificación no ocurre
 * aquí: lo dispara el módulo de Clasificación al escuchar el evento. Así el
 * registro sigue siendo la única fuente de verdad y el cálculo es siempre
 * derivado.
 */
final class RegisterRoundResult
{
    public function handle(Round $round, Registration $registration, RoundResultData $data): RoundResult
    {
        $this->guardRoundAcceptsResults($round);
        $this->guardRegistrationBelongsToRound($round, $registration);
        $this->guardRegistrationAcceptsResults($registration);
        $this->guardSuccessfulRoundHasTime($data);

        $result = DB::transaction(function () use ($round, $registration, $data): RoundResult {
            $this->guardResultNotAlreadyRecorded($round, $registration);

            try {
                return RoundResult::query()->create([
                    'round_id' => $round->id,
                    'registration_id' => $registration->id,
                    'recorded_by' => $data->recordedBy,
                    ...$data->toAttributes(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainException('Este inscrito ya tiene un resultado registrado en este lazo.');
            }
        });

        RoundResultRecorded::dispatch($result);

        return $result;
    }

    private function guardRoundAcceptsResults(Round $round): void
    {
        if (! $round->acceptsResults()) {
            throw new DomainException(
                'El lazo no está abierto para registrar resultados.',
                ['round' => "Estado actual del lazo: {$round->status->label()}."],
            );
        }
    }

    /**
     * Un resultado solo puede registrarse para una inscripción que pertenece
     * a la misma categoría que la ronda.
     */
    private function guardRegistrationBelongsToRound(Round $round, Registration $registration): void
    {
        if ($registration->tournament_category_id !== $round->categoryId()) {
            throw new DomainException(
                'La inscripción no pertenece a la categoría de este lazo.',
                ['registration' => 'Inscripción y lazo corresponden a categorías distintas.'],
            );
        }
    }

    private function guardRegistrationAcceptsResults(Registration $registration): void
    {
        if (! $registration->acceptsResults()) {
            throw new DomainException(
                'Esta inscripción no admite nuevos resultados.',
                ['registration' => "Estado de la inscripción: {$registration->status->label()}."],
            );
        }
    }

    /**
     * Una ronda acertada con tiempo cero no es un dato válido: sería
     * indistinguible de una ronda fallida en los cálculos.
     */
    private function guardSuccessfulRoundHasTime(RoundResultData $data): void
    {
        if ($data->successful && $data->time->isZero()) {
            throw new DomainException(
                'Un lazo acertado debe tener un tiempo mayor que cero.',
                ['time' => 'Ingrese el tiempo o marque el lazo como fallido.'],
            );
        }
    }

    private function guardResultNotAlreadyRecorded(Round $round, Registration $registration): void
    {
        $exists = RoundResult::query()
            ->where('round_id', $round->id)
            ->where('registration_id', $registration->id)
            ->exists();

        if ($exists) {
            throw new DomainException(
                'Este inscrito ya tiene un resultado registrado en este lazo. Use la corrección de resultados.',
            );
        }
    }
}
