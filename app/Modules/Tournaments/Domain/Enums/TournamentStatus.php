<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Domain\Enums;

enum TournamentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Published => 'Publicado',
            self::InProgress => 'En progreso',
            self::Finished => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Solo un torneo publicado, en progreso o finalizado es visible al público.
     * El borrador está siendo configurado y no debe filtrarse.
     */
    public function isPubliclyVisible(): bool
    {
        return in_array($this, [self::Published, self::InProgress, self::Finished], true);
    }

    public function isLive(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Un torneo cerrado no acepta cambios de estructura ni nuevas inscripciones.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Finished, self::Cancelled], true);
    }

    /**
     * Transiciones permitidas. El estado nunca salta arbitrariamente: la
     * competencia avanza en un solo sentido, salvo la cancelación.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::InProgress, self::Draft, self::Cancelled],
            self::InProgress => [self::Finished, self::Cancelled],
            self::Finished, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
