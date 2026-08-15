<?php

declare(strict_types=1);

namespace App\Modules\Competition\Application\Data;

use App\Modules\Shared\Domain\ValueObjects\Milliseconds;

/**
 * El dato que el administrador captura al cronometrar una ronda.
 *
 * La invariante vive aquí: un lazo fallido no lleva tiempo ni penalización. Se
 * normaliza en la construcción para que ningún camino de la aplicación pueda
 * persistir un resultado fallido con tiempo acumulable.
 */
final readonly class RoundResultData
{
    public Milliseconds $time;

    public Milliseconds $penalty;

    public function __construct(
        Milliseconds $time,
        public bool $successful,
        public ?string $notes = null,
        public ?int $recordedBy = null,
        ?Milliseconds $penalty = null,
    ) {
        // Lazo fallido => successful = false, time_ms = 0 y penalty_ms = 0.
        $this->time = $successful ? $time : Milliseconds::zero();
        $this->penalty = $successful ? ($penalty ?? Milliseconds::zero()) : Milliseconds::zero();
    }

    /**
     * Construye desde el formulario, donde el tiempo llega en segundos ("8.342").
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, ?int $recordedBy = null): self
    {
        $successful = (bool) ($validated['successful'] ?? true);

        return new self(
            time: $successful
                ? Milliseconds::fromSeconds($validated['time'] ?? '0')
                : Milliseconds::zero(),
            successful: $successful,
            notes: $validated['notes'] ?? null,
            recordedBy: $recordedBy,
            penalty: $successful
                ? Milliseconds::fromSeconds($validated['penalty'] ?? '0')
                : Milliseconds::zero(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'time_ms' => $this->time->value,
            'penalty_ms' => $this->penalty->value,
            'successful' => $this->successful,
            'notes' => $this->notes,
        ];
    }
}
