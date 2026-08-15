<?php

declare(strict_types=1);

namespace App\Modules\Tournaments\Domain\Enums;

enum CategoryStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::InProgress => 'En progreso',
            self::Finished => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * Una categoría cerrada conserva su clasificación pero ya no admite
     * inscripciones ni cambios en su estructura de días y rondas.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Finished, self::Cancelled], true);
    }

    public function acceptsRegistrations(): bool
    {
        return $this === self::Pending;
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
