<?php

declare(strict_types=1);

namespace App\Modules\Registrations\Domain\Enums;

enum RegistrationStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Withdrawn => 'Retirado',
            self::Disqualified => 'Descalificado',
        };
    }

    /**
     * Una inscripción retirada o descalificada conserva los resultados ya
     * registrados — son historial — pero no admite tiempos nuevos.
     */
    public function acceptsResults(): bool
    {
        return $this === self::Active;
    }

    /**
     * Solo las inscripciones activas compiten por una posición.
     */
    public function competesForStanding(): bool
    {
        return $this === self::Active;
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
