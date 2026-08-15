<?php

declare(strict_types=1);

namespace App\Modules\Competition\Domain\Enums;

enum RoundStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Active => 'En curso',
            self::Finished => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }

    /**
     * Solo se registran tiempos en una ronda abierta o ya finalizada — esta
     * última para permitir correcciones posteriores del administrador.
     */
    public function acceptsResults(): bool
    {
        return in_array($this, [self::Active, self::Finished], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
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
