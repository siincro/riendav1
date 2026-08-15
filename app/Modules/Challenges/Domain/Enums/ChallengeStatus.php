<?php

declare(strict_types=1);

namespace App\Modules\Challenges\Domain\Enums;

enum ChallengeStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'En juego',
            self::Settled => 'Resuelto',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Mientras el reto está en juego su ganador se recalcula con cada lazo;
     * al resolverlo queda congelado.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
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
